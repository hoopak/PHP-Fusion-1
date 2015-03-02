<?php

namespace PHPFusion\Forums;

/**
 * Forum Administration Console and functions
 * Class Admin
 * @package PHPFusion\Forums
 */
class Admin {

	/**
	 * todo: forum answering via ranks.. assign groups points.
	 * */

	private $ext = '';
	private $forum_index = array();
	private $level = array();
	private $data = array(
		'forum_id' => 0,
		'forum_cat' => 0,
		'forum_branch' => 0,
		'forum_name' => '',
		'forum_type' => '',
		'forum_answer_threshold' => 0,
		'forum_lock' => 0,
		'forum_order' => 0,
		'forum_description' => '',
		'forum_rules' => '',
		'forum_mods' => '',
		'forum_access' => '',
		'forum_post' => 101,
		'forum_reply' => 101,
		'forum_poll' => 101,
		'forum_vote' => 101,
		'forum_image' => '',
		'forum_post_ratings' => 101,
		'forum_users' => 0,
		'forum_allow_attach' => 101,
		'forum_attach' => 101,
		'forum_attach_download' => 101,
		'forum_quick_edit' => 1,
		'forum_laspostid' => 0,
		'forum_postcount' => 0,
		'forum_threadcount' => 0,
		'forum_lastuser' => 0,
		'forum_merge' => 0,
		'forum_language' => '',
		'forum_meta' => '',
		'forum_alias' => ''
	);

	public function __construct() {
		$_GET['forum_id'] =  (isset($_GET['forum_id']) && isnum($_GET['forum_id'])) ? $_GET['forum_id'] : 0;
		$_GET['forum_cat'] =  (isset($_GET['forum_cat']) && isnum($_GET['forum_cat'])) ? $_GET['forum_cat'] : 0;
		$_GET['forum_branch'] =  (isset($_GET['forum_branch']) && isnum($_GET['forum_branch'])) ? $_GET['forum_branch'] : 0;
		$_GET['parent_id'] =  (isset($_GET['parent_id']) && isnum($_GET['parent_id'])) ? $_GET['parent_id'] : 0;
		$_GET['action'] = (isset($_GET['action'])) && $_GET['action'] ? $_GET['action'] : '';
		$_GET['status'] = (isset($_GET['status'])) && $_GET['status'] ? $_GET['status'] : '';
		$this->ext = isset($_GET['parent_id']) && isnum($_GET['parent_id']) ? "&amp;parent_id=".$_GET['parent_id'] : '';
		$this->ext .= isset($_GET['branch']) && isnum($_GET['branch']) ? "&amp;branch=".$_GET['branch'] : '';

		$this->forum_index = self::get_forum_index();
		if (!empty($this->forum_index)) {
			$this->level = self::make_forum_breadcrumbs();
		}

		/**
		 * List of actions available in this admin
		 */
		self::forum_jump();
		self::remove_forum_image();
		self::remove_forumDB();
		self::set_forumPermissionsDB();
		self::set_forumDB();
		/**
		 * Ordering actions
		 */
		switch($_GET['action']) {
			case 'mu':
				self::move_up();
				break;
			case 'md':
				self::move_down();
				break;
			case 'delete':
				self::validate_forum_removal();
				break;
			case 'prune':
				require_once "forums_prune.php";
				break;
			case 'edit':
				$this->data = self::get_forum($_GET['forum_id']);
				break;
			case 'p_edit':
				$this->data = self::get_forum($_GET['forum_id']);
				break;
		}
	}

	/**
	 * Quick navigation jump.
	 */
	private function forum_jump() {
		global $aidlink;
		if (isset($_POST['jp_forum'])) {
			$data['forum_id'] = form_sanitizer($_POST['forum_id'], '', 'forum_id');
			redirect(FUSION_SELF.$aidlink."&amp;action=p_edit&amp;forum_id=".$data['forum_id']."&amp;parent_id=".$_GET['parent_id']);
		}
	}

	/**
	 * Get forum index for hierarchy traversal
	 * @return array
	 */
	private function get_forum_index() {
		return  dbquery_tree(DB_FORUMS, 'forum_id', 'forum_cat');
	}

	/**
	 * Breadcrumb and Directory Output Handler
	 * @return array
	 */
	private function make_forum_breadcrumbs() {
		global $aidlink, $locale;
		/* Make an infinity traverse */
		function breadcrumb_arrays($index, $id) {
			global $aidlink;
			$crumb = &$crumb;
			//$crumb += $crumb;
			if (isset($index[get_parent($index, $id)])) {
				$_name = dbarray(dbquery("SELECT forum_id, forum_name FROM ".DB_FORUMS." WHERE forum_id='".intval($id)."'"));
				$crumb = array('link'=>FUSION_SELF.$aidlink."&amp;parent_id=".$_name['forum_id'], 'title'=>$_name['forum_name']);
				if (isset($index[get_parent($index, $id)])) {
					if (get_parent($index, $id) == 0) {
						return $crumb;
					}
					$crumb_1 = breadcrumb_arrays($index, get_parent($index, $id));
					$crumb = array_merge_recursive($crumb, $crumb_1); // convert so can comply to Fusion Tab API.
				}
			}
			return $crumb;
		}
		// then we make a infinity recursive function to loop/break it out.
		$crumb = breadcrumb_arrays($this->forum_index, $_GET['parent_id']);
		// then we sort in reverse.
		if (count($crumb['title']) > 1)  { krsort($crumb['title']); krsort($crumb['link']); }
		add_to_breadcrumbs(array('link'=>FUSION_SELF.$aidlink, 'title'=>$locale['forum_000c']));
		if (count($crumb['title']) > 1) {
			foreach($crumb['title'] as $i => $value) {
				add_to_breadcrumbs(array('link'=>$crumb['link'][$i], 'title'=>$value));
			}
		} elseif (isset($crumb['title'])) {
			add_to_breadcrumbs(array('link'=>$crumb['link'], 'title'=>$crumb['title']));
		}
		return $crumb;
	}

	/**
	 * Authenticate valid forum id.
	 * @param $forum_id
	 * @return bool|string
	 */
	private function verify_forum($forum_id) {
		if (isnum($forum_id)) {
			return dbcount("('forum_id')", DB_FORUMS, "forum_id='".$forum_id."' AND ".groupaccess('forum_access')." ");
		}
		return false;
	}

	/**
	 * Get a forum full data
	 * @param $forum_id
	 * @return array|bool
	 */
	private function get_forum($forum_id) {
		if (self::verify_forum($forum_id)) {
			return dbarray(dbquery("SELECT * FROM ".DB_FORUMS." WHERE forum_id='".intval($forum_id)."' AND ".groupaccess('forum_access')." "));
		}
		return array();
	}

	/**
	 * Move forum order up a number
	 */
	private function move_up() {
		global $aidlink, $locale;
		if (isset($_GET['forum_id']) && isnum($_GET['forum_id']) && isset($_GET['order']) && isnum($_GET['order'])) {
			$data = dbarray(dbquery("SELECT forum_id FROM ".DB_FORUMS." ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_cat='".$_GET['parent_id']."' AND forum_order='".$_GET['order']."'"));
			$result = dbquery("UPDATE ".DB_FORUMS." SET forum_order=forum_order+1 ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$data['forum_id']."'");
			$result = dbquery("UPDATE ".DB_FORUMS." SET forum_order=forum_order-1 ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$_GET['forum_id']."'");
			notify($locale['forum_notice_6'], sprintf($locale['forum_notice_13'], $_GET['forum_id'], $_GET['order']));
			redirect(FUSION_SELF.$aidlink.$this->ext."&status=mup");
		}
	}

	/**
	 * Move forum order down a number
	 */
	private function move_down() {
		global $aidlink, $locale;
		if (isset($_GET['forum_id']) && isnum($_GET['forum_id']) && isset($_GET['order']) && isnum($_GET['order'])) {
			// fetches the id of the last forum.
			$data = dbarray(dbquery("SELECT forum_id FROM ".DB_FORUMS." ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_cat='".$_GET['parent_id']."' AND forum_order='".$_GET['order']."'"));
			$result = dbquery("UPDATE ".DB_FORUMS." SET forum_order=forum_order-1 ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$data['forum_id']."'");
			$result = dbquery("UPDATE ".DB_FORUMS." SET forum_order=forum_order+1 ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$_GET['forum_id']."'");
			notify($locale['forum_notice_7'], sprintf($locale['forum_notice_13'], $_GET['forum_id'], $_GET['order']));
		}
	}

	/**
	 * Delete checking
	 */
	private function validate_forum_removal() {
		global $aidlink;
		if (isset($_GET['forum_id']) && isnum($_GET['forum_id']) && isset($_GET['forum_cat']) && isnum($_GET['forum_cat'])) {
			// check if there are subforums, threads or posts.
			$forum_count = dbcount("('forum_id')", DB_FORUMS, "forum_cat='".$_GET['forum_id']."'");
			$thread_count = dbcount("('forum_id')", DB_FORUM_THREADS, "forum_id='".$_GET['forum_id']."'");
			$post_count = dbcount("('post_id')", DB_FORUM_THREADS, "forum_id='".$_GET['forum_id']."'");
			if (($forum_count+$thread_count+$post_count) >= 1) {
				self::display_forum_move_form();
			} else {
				self::prune_attachment($_GET['forum_id']);
				self::prune_posts($_GET['forum_id']);
				self::prune_threads($_GET['forum_id']);
				self::recalculate_post($_GET['forum_id']);
				self::prune_forums('', $_GET['forum_id']); // without index, this prune will delete only one.
				redirect(FUSION_SELF.$aidlink."&status=crf");
			}
		}
	}

	/**
	 * Remove a forum uploaded image
	 */
	private function remove_forum_image() {
		global $aidlink;
		if (isset($_POST['remove_image']) && isset($_POST['forum_id'])) {
			$data['forum_id'] = form_sanitizer($_POST['forum_id'], '', 'forum_id');
			if ($data['forum_id']) {
				$data = self::get_forum($data['forum_id']);
				if (!empty($data)) {
					if (!empty($data['forum_image']) && file_exists(IMAGES."forum/".$data['forum_image']) && !is_dir(IMAGES."forum/".$data['forum_image'])) {
						@unlink(IMAGES."forum/".$data['forum_image']);
						$data['forum_image'] = '';
					}
					dbquery_insert(DB_FORUMS, $data, 'update');
					redirect(FUSION_SELF.$aidlink."&status=rim");
				}
			}
		}
	}

	/**
	 * Remove forum SQL
	 */
	private function remove_forumDB() {
		global $defender, $locale, $aidlink;
		if (isset($_POST['forum_remove'])) {
			/**
			 * $action_data
			 * 'forum_id' - current forum id
			 * 'forum_branch' - the branch id
			 * 'threads_to_forum' - target destination where all threads should move to
			 * 'delete_threads' - if delete threads are checked
			 * 'subforum_to_forum' - target destination where all subforums should move to
			 * 'delete_forum' - if delete all subforums are checked
			 */
			$action_data = array(
				'forum_id' =>  isset($_POST['forum_id']) ? form_sanitizer($_POST['forum_id'], 0, 'forum_id') : 0,
				'forum_branch' => isset($_POST['forum_branch']) ? form_sanitizer($_POST['forum_branch'], 0, 'forum_branch') : 0,
				'threads_to_forum' => isset($_POST['move_threads']) ? form_sanitizer($_POST['move_threads'], 0, 'move_threads') : '',
				'delete_threads' =>  isset($_POST['delete_threads']) ? 1 : 0,
				'subforums_to_forum' => isset($_POST['move_forums']) ? form_sanitizer($_POST['move_forums'], 0, 'move_forums') : '',
				'delete_forums' => isset($_POST['delete_forums']) ? 1 : 0,
			);
			if (self::verify_forum($action_data['forum_id'])) {
				/**
				 * Threads and Posts action
				 */
				if (!$action_data['delete_threads'] && $action_data['threads_to_forum']) {
					dbquery("UPDATE ".DB_FORUM_THREADS." SET forum_id='".$action_data['threads_to_forum']."' WHERE forum_id='".$action_data['forum_id']."'");
					dbquery("UPDATE ".DB_FORUM_POSTS." SET forum_id='".$action_data['threads_to_forum']."' WHERE forum_id='".$action_data['forum_id']."'");
				}
				// wipe current forum and all threads
				elseif ($action_data['delete_threads']) {
					// remove all threads and all posts in this forum.
					self::prune_attachment($action_data['forum_id']); // wipe
					self::prune_posts($action_data['forum_id']); // wipe
					self::prune_threads($action_data['forum_id']); // wipe
					self::recalculate_post($action_data['forum_id']); // wipe
				} else {
					$defender->stop();
					$defender->addNotice($locale['forum_notice_na']);
				}

				/**
				 * Subforum action
				 */
				if (!$action_data['delete_forums'] && $action_data['subforums_to_forum']) {
					dbquery("UPDATE ".DB_FORUMS." SET forum_cat='".$action_data['subforums_to_forum']."', forum_branch='".get_hkey(DB_FORUMS, 'forum_id', 'forum_cat', $action_data['subforums_to_forum'])."'
					".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_cat='".$action_data['forum_id']."'");
				} elseif (!$action_data['delete_forums']) {
					$defender->stop();
					$defender->addNotice($locale['forum_notice_na']);
				}
			} else {
				$defender->stop();
				$defender->addNotice($locale['forum_notice_na']);
			}
			self::prune_forums($action_data['forum_id']);
			redirect(FUSION_SELF.$aidlink."&status=crf");
		}
	}

	/**
	 * Update Forum Permissions
	 */
	function set_forumPermissionsDB() {
		global $aidlink;
		if (isset($_POST['save_permission'])) {
			$this->data['forum_id'] = form_sanitizer($_POST['forum_id'], '', 'forum_id');
			$this->data = self::get_forum($this->data['forum_id']);
			if (!empty($this->data)) {
				$this->data['forum_access'] = form_sanitizer($_POST['forum_access'], 101, 'forum_access');
				$this->data['forum_post'] = form_sanitizer($_POST['forum_post'], 101, 'forum_post');
				$this->data['forum_reply'] = form_sanitizer($_POST['forum_reply'], 101, 'forum_reply');
				$this->data['forum_post_ratings'] = form_sanitizer($_POST['forum_post_ratings'], 101, 'forum_post_ratings');
				$this->data['forum_poll'] = form_sanitizer($_POST['forum_poll'], 101, 'forum_poll');
				$this->data['forum_vote'] = form_sanitizer($_POST['forum_vote'], 101, 'forum_vote');
				$this->data['forum_answer_threshold'] = form_sanitizer($_POST['forum_answer_threshold'], 0, 'forum_answer_threshold');
				$this->data['forum_attach'] = form_sanitizer($_POST['forum_attach'], 101, 'forum_attach');
				$this->data['forum_attach_download'] = form_sanitizer($_POST['forum_attach_download'], 101, 'forum_attach_download');
				$this->data['forum_mods'] = form_sanitizer($_POST['forum_mods'], '', 'forum_mods');
				dbquery_insert(DB_FORUMS, $this->data, 'update');
				if (!defined('FUSION_NULL')) redirect(FUSION_SELF.$aidlink.$this->ext."&amp;status=psv");
			}
		}
	}

	/**
	 * Return a valid forum name without duplicate
	 * @param     $forum_name
	 * @param int $forum_id
	 * @return mixed
	 */
	private function check_validForumName($forum_name, $forum_id = 0) {
		global $defender, $locale;
		// check forum name unique
		if ($forum_name) {
			if ($forum_id) {
				$name_check = dbcount("('forum_name')", DB_FORUMS, "forum_name='".$forum_name."' AND forum_id !='".$forum_id."'");
			} else {
				$name_check = dbcount("('forum_name')", DB_FORUMS, "forum_name='".$forum_name."'");
			}
			if ($name_check) {
				$defender->stop();
				$defender->addNotice($locale['forum_error_7']);
			} else {
				return $forum_name;
			}
		}
	}

	/**
	 * MYSQL update and save forum
	 */
	private function set_forumDB() {
		global $aidlink, $defender, $locale;
		if (isset($_POST['save_forum'])) {
			$this->data = array(
				'forum_id' => form_sanitizer($_POST['forum_id'], 0, 'forum_id'),
				'forum_name' => form_sanitizer($_POST['forum_name'], '', 'forum_name'),
				'forum_description' => form_sanitizer($_POST['forum_description'], '', 'forum_description'),
				'forum_cat' => form_sanitizer($_POST['forum_cat'], '', 'forum_cat'),
				'forum_type' => form_sanitizer($_POST['forum_type'], '', 'forum_type'),
				'forum_language' => form_sanitizer($_POST['forum_language'], '', 'forum_language'),
				'forum_alias' => form_sanitizer($_POST['forum_alias'], '', 'forum_alias'),
				'forum_meta' => form_sanitizer($_POST['forum_meta'], '', 'forum_meta'),
				'forum_rules' => form_sanitizer($_POST['forum_rules'], '', 'forum_rules'),
				'forum_image_enable' => isset($_POST['forum_image_enable']) ? 1 : 0,
				'forum_merge' => isset($_POST['forum_merge']) ? 1 : 0,
				'forum_allow_attach' => isset($_POST['forum_allow_attach']) ? 1 : 0,
				'forum_quick_edit' => isset($_POST['forum_quick_edit']) ? 1 : 0,
				'forum_poll' => isset($_POST['forum_poll']) ? 1 : 0,
				'forum_allow_ratings' => isset($_POST['forum_allow_ratings']) ? 1 : 0,
				'forum_users' => isset($_POST['forum_users']) ? 1 : 0,
				'forum_lock' => isset($_POST['forum_lock']) ? 1 : 0,
				'forum_permissions' => isset($_POST['forum_permissions']) ? form_sanitizer($_POST['forum_permissions'], 0, 'forum_permissions') : 0,
				'forum_order' => isset($_POST['forum_order']) ? form_sanitizer($_POST['forum_order']) : '',
				'forum_branch' =>  get_hkey(DB_FORUMS, 'forum_id', 'forum_cat', $this->data['forum_cat']),
				'forum_image' =>  '',
			);

			$this->data['forum_alias'] = $this->data['forum_alias'] ? str_replace(' ', '-', $this->data['forum_alias']) : '';

			// Checks for unique forum alias
			if ($this->data['forum_alias']) {
				if ($this->data['forum_id']) {
					$alias_check = dbcount("('alias_id')", DB_PERMALINK_ALIAS, "alias_url='".$this->data['forum_alias']."' AND alias_item_id !='".$this->data['forum_id']."'");
				} else {
					$alias_check = dbcount("('alias_id')", DB_PERMALINK_ALIAS, "alias_url='".$this->data['forum_alias']."'");
				}
				if ($alias_check) {
					$defender->stop();
					$defender->addNotice($locale['forum_error_6']);
				}
			}

			// check forum name unique
			$this->data['forum_name'] = self::check_validForumName($this->data['forum_name'], $this->data['forum_id']);

			// Uploads or copy forum image
			if (!empty($_FILES['forum_image']['name']) && is_uploaded_file($_FILES['forum_image']['tmp_name'])) {
				$upload = form_sanitizer($_FILES['forum_image'], '', 'forum_image');
				if ($upload['error'] == 0) {
					$this->data['forum_image'] = $upload['thumb1_name'];
				}
			} elseif (isset($_POST['forum_himage']) && $_POST['forum_himage'] != "") {
				// if not uploaded, here on both save and update.
				$type_opts = array('0'=>BASEDIR, '1'=>'http://', '2'=>'https://');
				$this->data['forum_image'] = $type_opts[form_sanitizer($_POST['forum_image_header'], '0', 'forum_image_header')].form_sanitizer($_POST['forum_himage'], '', 'forum_himage');

				if ($this->data['forum_id']) {
					$image_check = dbarray(dbquery("SELECT forum_image FROM ".DB_FORUMS." WHERE forum_id='".$this->data['forum_id']."'"));
					$image_found =  ($image_check['forum_image'] && file_exists(IMAGES."forum/".$image_check['forum_image'])) ? 1 : 0;
					if (!$image_found) {
						require_once INCLUDES."photo_functions_include.php";
						$upload = copy_file(IMAGES."forum/", $this->data['forum_image']);
					} else {
						$defender->stop();
						$defender->addNotice($locale['forum_error_8']);
					}
				} else {
					require_once INCLUDES."photo_functions_include.php";
					$upload = copy_file(IMAGES."forum/", $this->data['forum_image']);
				}
				if (isset($upload['error'])) {
					$defender->stop();
					$defender->addNotice($locale['forum_error_9']);
				} else {
					$this->data['forum_image'] = $upload['name'];
				}
			}

			// Set or copy forum_permissions
			if ($this->data['forum_permissions'] !=0) {
				$p_fields = dbarray(dbquery("SELECT
						forum_access, forum_post, forum_reply, forum_post_ratings, forum_poll, forum_vote, forum_answer_threshold, forum_attach, forum_attach_download, forum_mods
						FROM ".DB_FORUMS." WHERE forum_id='".$this->data['forum_permissions']."'
						"));
				$this->data += $p_fields;
			} else {
				$this->data += array(
					'forum_access' => 0,
					'forum_post' => 101,
					'forum_reply' => 101,
					'forum_post_ratings' => 101,
					'forum_poll' => 101,
					'forum_vote' => 101,
				);
			}
			// Set last order
			if (!$this->data['forum_order']) $this->data['forum_order'] = dbresult(dbquery("SELECT MAX(forum_order) FROM ".DB_FORUMS." ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_cat='".$this->data['forum_cat']."'"), 0)+1;

			if (self::verify_forum($this->data['forum_id'])) {
				$result = dbquery_order(DB_FORUMS, $this->data['forum_order'], 'forum_order', $this->data['forum_id'], 'forum_id', $this->data['forum_cat'], 'forum_cat', 1, 'forum_language', 'update');
				if ($result) {
					dbquery_insert(DB_FORUMS, $this->data, 'update');
				}
				if (!defined('FUSION_NULL')) redirect(FUSION_SELF.$aidlink.$this->ext."&amp;status=csup");
			} else {
				$new_forum_id = 0;
				$result = dbquery_order(DB_FORUMS, $this->data['forum_order'], 'forum_order', false, false, $this->data['forum_cat'], 'forum_cat', 1, 'forum_language', 'save');
				if ($result) {
					dbquery_insert(DB_FORUMS, $this->data, 'save', array('noredirect'=>1));
					$new_forum_id = dblastid();
				}
				if (!$this->data['forum_cat']) {
					redirect(FUSION_SELF.$aidlink."&amp;action=p_edit&amp;forum_id=".$new_forum_id."&amp;parent_id=0");
				} else {
					switch($this->data['forum_type']) {
						case '1':
							redirect(FUSION_SELF.$aidlink.$this->ext."&amp;status=cns");
							break;
						case '2':
							redirect(FUSION_SELF.$aidlink.$this->ext."&amp;status=cfs");
							break;
						case '3':
							redirect(FUSION_SELF.$aidlink.$this->ext."&amp;status=cls");
							break;
						case '4':
							redirect(FUSION_SELF.$aidlink.$this->ext."&amp;status=cas");
							break;
					}
				}
			}
		}
	}

	/**
	 * Show Status Messages
	 */
	private function display_forum_message() {
		global $locale;
		$message = '';
		switch($_GET['status']) {
			case 'cns':
				$message = $locale['forum_notice_1'];
				break;
			case 'cfs':
				$message = $locale['forum_notice_2'];
				break;
			case 'cls':
				$message = $locale['forum_notice_3'];
				break;
			case 'cas':
				$message = $locale['forum_notice_4'];
				break;
			case 'crf':
				$message = $locale['forum_notice_5'];
				break;
			case 'mup':
				$message = $locale['forum_notice_6'];
				break;
			case 'md':
				$message = $locale['forum_notice_7'];
				break;
			case 'rim':
				$message = $locale['forum_notice_8'];
				break;
			case 'csup':
				$message = $locale['forum_notice_9'];
				break;
			case 'psv':
				$message = $locale['forum_notice_10'];
				break;
		}

		if ($message) {
			echo admin_message($message);
		}
	}

	/**
	 * Forum Admin Main Template Output
	 */
	public function display_forum_admin() {
		global $defender, $locale;
		$res = 0;
		if (isset($_POST['init_forum'])) {
			$this->data['forum_name'] = self::check_validForumName(form_sanitizer($_POST['forum_name'], '', 'forum_name'), 0);
			if ($this->data['forum_name']) {
				$res = 1;
				$this->data['forum_cat'] = isset($_GET['parent_id']) && isnum($_GET['parent_id']) ? $_GET['parent_id'] : 0;
			}
		}
		if ($res == 1 or (isset($_POST['save_forum']) && defined('FUSION_NULL')) or isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['forum_id']) && isnum($_GET['forum_id'])) {
			// show forum creation form
			self::display_forum_form();
		} elseif (isset($_GET['action']) && $_GET['action'] == 'p_edit' && isset($_GET['forum_id']) && isnum($_GET['forum_id'])) {
			// show forum permissions form
			self::display_forum_permissions_form();
		} else {
			// index page
			if (defined('FUSION_NULL')) {
				echo $defender->showNotice();
			}
			self::display_forum_jumper();
			self::display_forum_message();
			self::display_forum_list();
			self::quick_create_forum();
		}
	}

	/**
	 * Js menu jumper
	 */
	private function display_forum_jumper() {
		/* JS Menu Jumper */
		global $aidlink, $locale;
		echo "<div class='clearfix'>\n";
		echo form_select_tree('', 'forum_jump', 'forum_jump', $_GET['parent_id'], array('inline'=>1,  'class'=>'pull-right', 'parent_value'=>$locale['forum_root']), DB_FORUMS, 'forum_name', 'forum_id', 'forum_cat');
		echo "<label for='forum_jump' class='text-dark strong pull-right m-r-10 m-t-3'>".$locale['forum_044']."</label>\n";
		add_to_jquery("
		$('#forum_jump').change(function() {
			location = '".FUSION_SELF.$aidlink."&parent_id='+$(this).val();
		});
		");
		echo "</div>\n";
	}

	/**
	 * Display Forum Form
	 */
	private function display_forum_form() {
		global $aidlink, $settings, $locale;
		$language_opts = fusion_get_enabled_languages();
		add_to_breadcrumbs(array('link'=>'', 'title'=>$locale['forum_001']));
		if (!isset($_GET['action']) && $_GET['parent_id']) {
			$data['forum_cat'] = $_GET['parent_id'];
		}
		$type_opts = array('1'=>$locale['forum_opts_001'], '2'=>$locale['forum_opts_002'], '3'=>$locale['forum_opts_003'], '4'=>$locale['forum_opts_004']);
		opentable($locale['forum_001']);
		echo openform('inputform', 'inputform', 'post', FUSION_SELF.$aidlink.$this->ext, array('enctype'=>1, 'downtime'=>1));
		echo "<div class='row'>\n<div class='col-xs-12 col-sm-8 col-md-8 col-lg-8'>\n";
		echo form_text($locale['forum_006'], 'forum_name', 'forum_name', $this->data['forum_name'], array('required'=>1, 'error_text'=>$locale['forum_error_1']));
		echo form_textarea($locale['forum_007'], 'forum_description', 'forum_description', $this->data['forum_description'], array('autosize'=>1, 'bbcode'=>1));
		echo "</div><div class='col-xs-12 col-sm-4 col-md-4 col-lg-4'>\n";
		openside('');
		$self_id = $this->data['forum_id'] ? $this->data['forum_id'] : '';
		echo form_select_tree($locale['forum_008'], 'forum_cat', 'forum_cat', $this->data['forum_cat'], array('add_parent_opts'=>1, 'disable_opts'=>$self_id, 'hide_disabled'=>1), DB_FORUMS, 'forum_name', 'forum_id', 'forum_cat', $self_id);
		echo form_select($locale['forum_009'], 'forum_type', 'forum_type', $type_opts, $this->data['forum_type']);
		echo form_select($locale['forum_010'], 'forum_language', 'forum_lang', $language_opts, $this->data['forum_language']);
		echo form_text($locale['forum_043'], 'forum_order', 'forum_order', $this->data['forum_order'], array('number'=>1));
		echo form_button($this->data['forum_id'] ? $locale['forum_000a'] : $locale['forum_000'], 'save_forum', 'save_forum', $locale['forum_000'], array('class'=>'btn btn-sm btn-success'));
		closeside();
		echo "</div>\n</div>\n";
		echo "<div class='row'>\n<div class='col-xs-12 col-sm-8 col-md-8 col-lg-8'>\n";
		echo form_text($locale['forum_011'], 'forum_alias', 'forum_alias', $this->data['forum_alias']); // need ajax check
		echo form_select($locale['forum_012'], 'forum_meta', 'forum_meta', array(), $this->data['forum_meta'], array('tags'=>1, 'multiple'=>1, 'width'=>'100%'));
		// possible bug? - if image is tied to a url. we can remove it after assigning to other's people page? what if i split to image_url ?
		if ($this->data['forum_image'] && file_exists(IMAGES."forum/".$this->data['forum_image'])) {
			openside();
			echo "<div class='pull-left m-r-10'>\n";
			$image_size = getimagesize(IMAGES."forum/".$this->data['forum_image']);
			echo thumbnail(IMAGES."forum/".$this->data['forum_image'], '80px', '80px');
			echo "</div>\n<div class='overflow-hide'>\n";
			echo "<span class='strong'>".$locale['forum_013']."</span><br/>\n";
			echo "<span class='text-smaller'>".sprintf($locale['forum_027'], $image_size[0], $image_size[1])."</span><br/>";
			echo form_button($locale['forum_028'], 'remove_image', 'remove_image', $locale['forum_028'], array('class'=>'btn-danger btn-xs m-t-10', 'icon'=>'fa fa-trash'));
			// this form has forum_id - onclick of button - will also post forum_id @ L475
			echo "</div>\n";
			closeside();
		} else {
			$tab_title['title'][] = $locale['forum_013'];
			$tab_title['id'][] = 'fir';
			$tab_title['icon'][] = '';
			$tab_title['title'][] = $locale['forum_014'];
			$tab_title['id'][] = 'ful';
			$tab_title['icon'][] = '';
			$tab_active = tab_active($tab_title, 0);
			echo opentab($tab_title, $tab_active, 'forum-image-tab');
			echo opentabbody($tab_title['title'][0], 'fir', $tab_active);
			echo "<span class='display-inline-block m-t-10 m-b-10'>".sprintf($locale['forum_015'], parsebytesize($settings['download_max_b']))."</span>\n";
			echo form_fileinput('', 'forum_image', 'forum_image', IMAGES."forum", '', array('thumbnail'=>IMAGES."forum/thumbnail", 'type'=>'image'));
			echo closetabbody();
			echo opentabbody($tab_title['title'][1], 'ful', $tab_active);
			echo "<span class='display-inline-block m-t-10 m-b-10'>".$locale['forum_016']."</strong></span>\n";
			$header_opts = array(
				'0' => $settings['siteurl'],
				'1' => 'http://',
				'2' => 'https://'
			);
			echo form_select($locale['forum_056'], 'forum_image_header', 'forum_image_header', $header_opts, '', array('inline'=>1));
			echo form_text($locale['forum_014'], 'forum_image', 'forum_image_url', '', array('placeholder'=>'images/forum/', 'inline'=>1));
			echo closetabbody();
			echo closetab();
		}

		echo form_textarea($locale['forum_017'], 'forum_rules', 'forum_rules', $this->data['forum_rules'], array('autosize'=>1, 'bbcode'=>1));
		echo "</div><div class='col-xs-12 col-sm-4 col-md-4 col-lg-4'>\n";
		openside('');
		echo form_select_tree($locale['forum_025'], 'forum_permissions', 'forum_permissions', '', array('no_root'=>1), DB_FORUMS, 'forum_name', 'forum_id', 'forum_cat');
		if ($this->data['forum_id']) {
			echo form_button($locale['forum_029'], 'jp_forum', 'jp_forum', $locale['forum_029'], array('class'=>'btn-sm btn-default m-r-10'));
		}
		closeside();
		openside('');
		echo form_checkbox($locale['forum_026'], 'forum_lock', 'forum_lock', $this->data['forum_lock']);
		echo form_checkbox($locale['forum_024'], 'forum_users', 'forum_users', $this->data['forum_users']);
		echo form_checkbox($locale['forum_021'], 'forum_quick_edit', 'forum_quick_edit', $this->data['forum_quick_edit']);
		echo form_checkbox($locale['forum_019'], 'forum_merge', 'forum_merge', $this->data['forum_merge']);
		echo form_checkbox($locale['forum_020'], 'forum_allow_attach', 'forum_allow_attach', $this->data['forum_attach']);
		echo form_checkbox($locale['forum_022'], 'forum_poll', 'forum_poll', $this->data['forum_poll']);
		echo form_checkbox($locale['forum_023'], 'forum_post_ratings', 'forum_post_ratings', $this->data['forum_post_ratings']);
		echo form_hidden('', 'forum_id', 'forum_id', $this->data['forum_id']);
		echo form_hidden('', 'forum_branch', 'forum_branch', $this->data['forum_branch']);
		closeside();
		echo "</div>\n</div>\n";
		echo form_button($this->data['forum_id'] ? $locale['forum_000a'] : $locale['forum_000'], 'save_forum', 'save_forum_1', $locale['forum_000'], array('class'=>'btn-sm btn-success'));
		echo closeform();
		closetable();
	}

	/**
	 * Permissions Form
	 */
	private function display_forum_permissions_form() {
		global $aidlink, $locale;
		$data = $this->data;

		$data += array(
			'forum_id' => !empty($data['forum_id']) && isnum($data['forum_id']) ? $data['forum_id'] : 0,
			'forum_type' => !empty($data['forum_type']) ? $data['forum_type'] : '', // redirect if not exist? no..
		);
		add_to_breadcrumbs(array('link'=>'', 'title'=>$locale['forum_030']));
		opentable($locale['forum_030']);
		$_access = getusergroups();
		$access_opts['0'] = $locale['531'];
		while (list($key, $option) = each($_access)) {
			$access_opts[$option['0']] = $option['1'];
		}

		echo openform('inputform', 'inputform', 'post', FUSION_SELF.$aidlink.$this->ext."&amp;action=p_edit&amp;forum_id=".$_GET['forum_id'], array('enctype'=>1, 'downtime'=>1));
		echo "<span class='strong display-inline-block m-b-20'>".$locale['forum_006']." : ".$data['forum_name']."</span>\n";
		openside();
		echo "<span class='text-dark strong display-inline-block m-b-20'>".$locale['forum_desc_000']."</span><br/>\n";
		echo form_select($locale['forum_031'], 'forum_access', 'forum_access', $access_opts, $data['forum_access'], array('inline'=>1));
		unset($access_opts[0]); // remove public away.
		echo form_select($locale['forum_032'], 'forum_post', 'forum_post', $access_opts, $data['forum_post'], array('inline'=>1));
		echo form_select($locale['forum_033'], 'forum_reply', 'forum_reply', $access_opts, $data['forum_reply'], array('inline'=>1));
		echo form_select($locale['forum_039'], 'forum_post_ratings', 'forum_post_ratings', $access_opts, $data['forum_post_ratings'], array('inline'=>1));
		closeside();

		openside();
		echo "<span class='text-dark strong display-inline-block m-b-20'>".$locale['forum_desc_001']."</span><br/>\n";
		echo form_select($locale['forum_036'], 'forum_poll', 'forum_poll', $access_opts, $data['forum_poll'], array('inline'=>1));
		echo form_select($locale['forum_037'], 'forum_vote', 'forum_vote', $access_opts, $data['forum_vote'], array('inline'=>1));
		closeside();

		openside();
		echo "<span class='text-dark strong display-inline-block m-b-20'>".$locale['forum_desc_004']."</span><br/>\n";
		$selection = array(
			$locale['forum_041'],
			"10 ".$locale['forum_points'],
			"20 ".$locale['forum_points'],
			"30 ".$locale['forum_points'],
			"40 ".$locale['forum_points'],
			"50 ".$locale['forum_points'],
			"60 ".$locale['forum_points'],
			"70 ".$locale['forum_points'],
			"80 ".$locale['forum_points'],
			"90 ".$locale['forum_points'],
			"100 ".$locale['forum_points']
		);
		echo form_select($locale['forum_040'], 'forum_answer_threshold', 'forum_answer_threshold', $selection, $data['forum_answer_threshold'], array('inline'=>1));
		closeside();

		openside();
		echo "<span class='text-dark strong display-inline-block m-b-20'>".$locale['forum_desc_002']."</span><br/>\n";
		echo form_select($locale['forum_034'], 'forum_attach', 'forum_attach', $access_opts, $data['forum_attach'], array('inline'=>1));
		echo form_select($locale['forum_035'], 'forum_attach_download', 'forum_attach_download', $access_opts, $data['forum_attach_download'], array('inline'=>1));
		closeside();

		openside();
		echo "<span class='text-dark strong display-inline-block m-b-20'>".$locale['forum_desc_003']."</span><br/>\n";
		$mod_groups = getusergroups();
		$mods1_user_id = array();
		$mods1_user_name = array();
		while (list($key, $mod_group) = each($mod_groups)) {
			if ($mod_group['0'] != "0" && $mod_group['0'] != "101" && $mod_group['0'] != "103") {
				if (!preg_match("(^{$mod_group['0']}$|^{$mod_group['0']}\.|\.{$mod_group['0']}\.|\.{$mod_group['0']}$)", $data['forum_mods'])) {
					$mods1_user_id[] = $mod_group['0'];
					$mods1_user_name[] = $mod_group['1'];
				} else {
					$mods2_user_id[] = $mod_group['0'];
					$mods2_user_name[] = $mod_group['1'];
				}
			}
		}
		echo "<div class='row'>\n<div class='col-xs-12 col-sm-6 col-md-6 col-lg-6'>\n";
		echo "<select multiple='multiple' size='10' name='modlist1' id='modlist1' class='form-control textbox m-r-10' onchange=\"addUser('modlist2','modlist1');\">\n";
		for ($i = 0; $i < count($mods1_user_id); $i++) {
			echo "<option value='".$mods1_user_id[$i]."'>".$mods1_user_name[$i]."</option>\n";
		}
		echo "</select>\n";
		echo "</div>\n<div class='col-xs-12 col-sm-6 col-md-6 col-lg-6'>\n";
		echo "<select multiple='multiple' size='10' name='modlist2' id='modlist2' class='form-control textbox' onchange=\"addUser('modlist1','modlist2');\">\n";
		if (isset($mods2_user_id) && is_array($mods2_user_id)) {
			for ($i = 0; $i < count($mods2_user_id); $i++) {
				echo "<option value='".$mods2_user_id[$i]."'>".$mods2_user_name[$i]."</option>\n";
			}
		}
		echo "</select>\n";
		echo form_hidden('', 'forum_mods', 'forum_mods', $data['forum_mods']);
		echo form_hidden('', 'forum_id', 'forum_id', $data['forum_id']);
		echo "</div>\n</div>\n";
		closeside();

		echo form_button($locale['forum_042'], 'save_permission', 'save_permission', $locale['forum_042'], array('class' =>'btn-primary btn-sm'));

		add_to_jquery(" $('#save').bind('click', function() { saveMods(); }); ");
		echo "<script type='text/javascript'>\n"."function addUser(toGroup,fromGroup) {\n";
		echo "var listLength = document.getElementById(toGroup).length;\n";
		echo "var selItem = document.getElementById(fromGroup).selectedIndex;\n";
		echo "var selText = document.getElementById(fromGroup).options[selItem].text;\n";
		echo "var selValue = document.getElementById(fromGroup).options[selItem].value;\n";
		echo "var i; var newItem = true;\n";
		echo "for (i = 0; i < listLength; i++) {\n";
		echo "if (document.getElementById(toGroup).options[i].text == selText) {\n";
		echo "newItem = false; break;\n}\n}\n"."if (newItem) {\n";
		echo "document.getElementById(toGroup).options[listLength] = new Option(selText, selValue);\n";
		echo "document.getElementById(fromGroup).options[selItem] = null;\n}\n}\n";
		echo "function saveMods() {\n"."var strValues = \"\";\n";
		echo "var boxLength = document.getElementById('modlist2').length;\n";
		echo "var count = 0;\n"."	if (boxLength != 0) {\n"."for (i = 0; i < boxLength; i++) {\n";
		echo "if (count == 0) {\n"."strValues = document.getElementById('modlist2').options[i].value;\n";
		echo "} else {\n"."strValues = strValues + \".\" + document.getElementById('modlist2').options[i].value;\n";
		echo "}\n"."count++;\n}\n}\n";
		echo "if (strValues.length == 0) {\n"."document.forms['inputform'].submit();\n";
		echo "} else {\n"."document.forms['inputform'].forum_mods.value = strValues;\n";
		echo "document.forms['inputform'].submit();\n}\n}\n</script>\n";
		closetable();
	}

	/**
	 * Quick create
	 */
	private function quick_create_forum() {
		global $aidlink, $locale;
		opentable($locale['forum_001']);
		echo openform('inputform', 'inputform', 'post', FUSION_SELF.$aidlink.$this->ext, array('downtime'=>1, 'notice'=>0));
		echo form_text($locale['forum_006'], 'forum_name', 'forum_name', '', array('required'=>1, 'inline'=>1, 'placeholder'=>$locale['forum_018']));
		echo form_button($locale['forum_001'], 'init_forum', 'init_forum', 'init_forum', array('class'=>'btn btn-sm btn-primary'));
		echo closeform();
		closetable();
	}

	/**
	 * Forum Listing
	 */
	private function display_forum_list() {
		global $locale, $aidlink, $settings;
		$title = !empty($this->level) ? sprintf($locale['forum_000b'], $this->level['title']) : $locale['forum_000c'];
		add_to_title($title.$locale['global_201']);
		opentable($title);
		$threads_per_page = $settings['threads_per_page'];
		$max_rows = dbcount("('forum_id')", DB_FORUMS, (multilang_table("FO") ? "forum_language='".LANGUAGE."' AND" : '')." forum_cat='".$_GET['parent_id']."'"); // need max rows
		$_GET['rowstart'] = (isset($_GET['rowstart']) && $_GET['rowstart'] <= $max_rows) ? $_GET['rowstart'] : '0';
		$result = dbquery("SELECT forum_id, forum_cat, forum_branch, forum_name, forum_description, forum_image, forum_alias, forum_type, forum_threadcount, forum_postcount, forum_order FROM
				".DB_FORUMS." ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_cat='".$_GET['parent_id']."'
				 ORDER BY forum_order ASC LIMIT ".$_GET['rowstart'].", $threads_per_page
				 ");
		$rows = dbrows($result);
		if ($rows > 0) {
			$type_icon = array('1'=>'entypo folder', '2'=>'entypo chat', '3'=>'entypo link', '4'=>'entypo graduation-cap');
			$i = 1;
			while ($data = dbarray($result)) {
				$up = $data['forum_order']-1;
				$down = $data['forum_order']+1;
				echo "<div class='panel panel-default'>\n";
				echo "<div class='panel-body'>\n";
				echo "<div class='pull-left m-r-10'>\n";
				echo "<i class='".$type_icon[$data['forum_type']]." icon-sm'></i>\n";
				echo "</div>\n";
				echo "<div class='overflow-hide'>\n";
				echo "<div class='row'>\n";
				echo "<div class='col-xs-6 col-sm-6 col-md-6 col-lg-6'>\n";
				$html2 = '';
				if ($data['forum_image'] && file_exists(IMAGES."forum/".$data['forum_image'])) {
					echo "<div class='pull-left m-r-10'>\n".thumbnail(IMAGES."forum/".$data['forum_image'], '50px')."</div>\n";
					echo "<div class='overflow-hide'>\n";
					$html2 = "</div>\n";
				}
				echo "<span class='strong text-bigger'><a href='".FUSION_SELF.$aidlink."&amp;parent_id=".$data['forum_id']."&amp;branch=".$data['forum_branch']."'>".$data['forum_name']."</a></span><br/>".$data['forum_description'].$html2;
				echo "</div>\n<div class='col-xs-6 col-sm-6 col-md-6 col-lg-6'>\n";
				echo "<div class='pull-right'>\n";
				$upLink = FUSION_SELF.$aidlink.$this->ext."&amp;action=mu&amp;order=$up&amp;forum_id=".$data['forum_id'];
				$downLink = FUSION_SELF.$aidlink.$this->ext."&amp;action=md&amp;order=$down&amp;forum_id=".$data['forum_id'];
				echo ($i == 1) ? '' : "<a title='".$locale['forum_045']."' href='".$upLink."'><i class='entypo up-bold m-l-0 m-r-0' style='font-size:18px; padding:0; line-height:14px;'></i></a>";
				echo ($i == $rows) ? '' : "<a title='".$locale['forum_046']."' href='".$downLink."'><i class='entypo down-bold m-l-0 m-r-0' style='font-size:18px; padding:0; line-height:14px;'></i></a>";
				echo "<a title='".$locale['forum_047']."' href='".FUSION_SELF.$aidlink."&amp;action=p_edit&forum_id=".$data['forum_id']."&amp;parent_id=".$_GET['parent_id']."'><i class='entypo key m-l-0 m-r-0' style='font-size:18px; padding:0; line-height:14px;'></i></a>"; // edit
				echo "<a title='".$locale['forum_048']."' href='".FUSION_SELF.$aidlink."&amp;action=edit&forum_id=".$data['forum_id']."&amp;parent_id=".$_GET['parent_id']."'><i class='entypo cog m-l-0 m-r-0' style='font-size:18px; padding:0; line-height:14px;'></i></a>"; // edit
				echo "<a title='".$locale['forum_049']."' href='".FUSION_SELF.$aidlink."&amp;action=delete&amp;forum_id=".$data['forum_id']."&amp;forum_cat=".$data['forum_cat']."&amp;forum_branch=".$data['forum_branch'].$this->ext."' onclick=\"return confirm('".$locale['delete_notice']."');\"><i class='entypo icancel m-l-0 m-r-0' style='font-size:18px; padding:0; line-height:14px;'></i></a>"; // delete
				echo "</div>\n";
				echo "<span class='text-dark text-smaller strong'>Topics: ".number_format($data['forum_threadcount'])." / Posts: ".number_format($data['forum_postcount'])." </span>\n<br/>";
				$subforums = get_child($this->forum_index, $data['forum_id']);
				$subforums = !empty($subforums) ? count($subforums) : 0;
				echo "<span class='text-dark text-smaller strong'>".$locale['forum_050'].": ".number_format($subforums)."</span>\n<br/>";
				echo "<span class='text-smaller text-dark strong'>".$locale['forum_051'].":</span> <span class='text-smaller'>".$data['forum_alias']." </span>\n";
				echo "</div></div>\n"; // end row
				echo "</div>\n";
				echo "</div>\n</div>\n";
				$i++;
			}
			if ($max_rows > $threads_per_page) {
				$ext = (isset($_GET['parent_id'])) ? "&amp;parent_id=".$_GET['parent_id']."&amp;" : '';
				echo makepagenav($_GET['rowstart'], $threads_per_page, $max_rows, 3, FUSION_SELF.$aidlink.$ext);
			}
		} else {
			echo "<div class='well text-center'>".$locale['560']."</div>\n";
		}
		closetable();
	}

	/**
	 * HTML template for forum move
	 */
	private	function display_forum_move_form() {
		global $aidlink, $locale;
		echo openmodal('move', $locale['forum_060'], array('static'=>1, 'class'=>'modal-md'));
		echo openform('moveform', 'moveform', 'post', FUSION_SELF.$aidlink.$this->ext, array('downtime' => 1));
		echo "<div class='row'>\n";
		echo "<div class='col-xs-12 col-sm-5 col-md-5 col-lg-5'>\n";
		echo "<span class='text-dark strong'>".$locale['forum_052']."</span><br/>\n";
		echo "</div><div class='col-xs-12 col-sm-7 col-md-7 col-lg-7'>\n";
		echo form_select_tree('', 'move_threads', 'move_threads', $_GET['forum_id'], array('width'=>'100%', 'inline'=>1, 'disable_opts'=>$_GET['forum_id'], 'hide_disabled'=>1, 'no_root'=>1), DB_FORUMS, 'forum_name', 'forum_id', 'forum_cat', $_GET['forum_id']);
		echo form_checkbox($locale['forum_053'], 'delete_threads', 'delete_threads', '');
		echo "</div>\n</div>\n";
		echo "<div class='row'>\n";
		echo "<div class='col-xs-12 col-sm-5 col-md-5 col-lg-5'>\n";
		echo "<span class='text-dark strong'>".$locale['forum_054']."</span><br/>\n"; // if you move, then need new hcat_key
		echo "</div><div class='col-xs-12 col-sm-7 col-md-7 col-lg-7'>\n";
		echo form_select_tree('', 'move_forums', 'move_forums', $_GET['forum_id'], array('width'=>'100%', 'inline'=>1, 'disable_opts'=>$_GET['forum_id'], 'hide_disabled'=>1, 'no_root'=>1), DB_FORUMS, 'forum_name', 'forum_id', 'forum_cat', $_GET['forum_id']);
		echo form_checkbox($locale['forum_055'], 'delete_forums', 'delete_forums', '');
		echo "</div>\n</div>\n";
		echo "<div class='clearfix'>\n";
		echo form_hidden('', 'forum_remove', 'forum_remove', 1); // key to launch next sequence
		echo form_hidden('', 'forum_id', 'forum_id', $_GET['forum_id']);
		echo form_hidden('', 'forum_branch', 'forum_branch', $_GET['forum_branch']);
		echo form_button($locale['forum_049'], 'submit_move', 'submit_move', 'submit_move', array('class'=>'btn-sm btn-danger m-r-10', 'icon'=>'fa fa-trash'));
		echo "<button type='button' class='btn btn-sm btn-default' data-dismiss='modal'><i class='entypo cross'></i> ".$locale['close']."</button>\n";
		echo "</div>\n";
		echo closeform();
		echo closemodal();
	}

	/** Prune functions */

	/**
	 * Delete all forum attachments
	 * @param      $forum_id
	 * @param bool $time
	 * @return string
	 */
	static function prune_attachment($forum_id, $time=false) {
		global $locale;
		// delete attachments.
		$result = dbquery("SELECT post_id, post_datestamp FROM ".DB_FORUM_POSTS." WHERE forum_id='".$forum_id."' ".($time ? "AND post_datestamp < '".$time."'" : '')."");
		$delattach = 0;
		if (dbrows($result)>0) {
			while ($data = dbarray($result)) {
				// delete all attachments
				$result2 = dbquery("SELECT attach_name FROM ".DB_FORUM_ATTACHMENTS." WHERE post_id='".$data['post_id']."'");
				if (dbrows($result2) != 0) {
					$delattach++;
					$attach = dbarray($result2);
					@unlink(FORUM."attachments/".$attach['attach_name']);
					$result3 = dbquery("DELETE FROM ".DB_FORUM_ATTACHMENTS." WHERE post_id='".$data['post_id']."'");
				}
			}
		}
		return $locale['610'].$delattach;
	}

	/**
	 * Delete all forum posts
	 * @param      $forum_id
	 * @param bool $time
	 * @return string
	 */
	static function prune_posts($forum_id, $time=false) {
		global $locale;
		// delete posts.
		$result = dbquery("DELETE FROM ".DB_FORUM_POSTS." WHERE forum_id='".$forum_id."' ".($time ? "AND post_datestamp < '".$time."'" : '')."");
		return $locale['609'].mysql_affected_rows();
	}

	/**
	 * Delete all forum threads
	 * @param      $forum_id
	 * @param bool $time
	 */
	static function prune_threads($forum_id, $time=false) {
		// delete follows on threads
		$result = dbquery("SELECT thread_id, thread_lastpost FROM ".DB_FORUM_THREADS." WHERE forum_id='".$forum_id."' ".($time ? "AND thread_lastpost < '".$time."'" : '')." ");
		if (dbrows($result)) {
			while ($data = dbarray($result)) {
				$result2 = dbquery("DELETE FROM ".DB_FORUM_THREAD_NOTIFY." WHERE thread_id='".$data['thread_id']."'");
			}
		}
		// delete threads
		$result = dbquery("DELETE FROM ".DB_FORUM_THREADS." WHERE forum_id='$forum_id' ".($time ? "AND thread_lastpost < '".$time."'" : '')." ");
	}

	/**
	 * Remove the entire forum branch, image and order updated
	 * @param bool $branch_data -- now as entire $this->index
	 * @param bool $index
	 * @param bool $time
	 */
	static function prune_forums($index = FALSE, $time = FALSE) {
		// delete forums - wipeout branch, image, order updated.
		$index = $index ? $index : 0;
		// need to refetch a new index after moving, else the id will be targetted
		$branch_data = self::get_forum_index();
		//print_p($branch_data[$index]);
		//print_p("Index is $index");
		$index_data = dbarray(dbquery("SELECT forum_id, forum_image, forum_order FROM ".DB_FORUMS." ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$index."'"));
		// check if there is a sub for this node.
		if (isset($branch_data[$index])) {
			foreach($branch_data[$index] as $forum_id) {
				//print_p("child forum id is $forum_id");
				$data = dbarray(dbquery("SELECT forum_id, forum_image, forum_order FROM ".DB_FORUMS." ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$forum_id."'"));
				if ($data['forum_image'] && file_exists(IMAGES."forum/".$data['forum_image'])) {
					unlink(IMAGES."forum/".$data['forum_image']);
					//print_p("unlinked ".$data['forum_image']."");
				}
				dbquery("UPDATE ".DB_FORUMS." SET forum_order=forum_order-1 ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$forum_id."' AND forum_order>'".$data['forum_order']."'");
				//print_p("deleted ".$forum_id."");
				dbquery("DELETE FROM ".DB_FORUMS." ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='$forum_id' ".($time ? "AND forum_lastpost < '".$time."'" : '')." ");
				if (isset($branch_data[$data['forum_id']])) {
					self::prune_forums($branch_data, $data['forum_id'], $time);
				}
				// end foreach
			}
			// finally remove itself.
			if ($index_data['forum_image'] && file_exists(IMAGES."forum/".$index_data['forum_image'])) {
				unlink(IMAGES."forum/".$data['forum_image']);
				//print_p("unlinked ".$index_data['forum_image']."");
			}
			dbquery("UPDATE ".DB_FORUMS." SET forum_order=forum_order-1 ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$index."' AND forum_order>'".$index_data['forum_order']."'");
			//print_p("deleted ".$index."");
			dbquery("DELETE FROM ".DB_FORUMS." ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$index."' ".($time ? "AND forum_lastpost < '".$time."'" : '')." ");
		} else {
			if ($index_data['forum_image'] && file_exists(IMAGES."forum/".$index_data['forum_image'])) {
				unlink(IMAGES."forum/".$index_data['forum_image']);
				//print_p("unlinked ".$index_data['forum_image']."");
			}
			dbquery("UPDATE ".DB_FORUMS." SET forum_order=forum_order-1 ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$index."' AND forum_order>'".$index_data['forum_order']."'");
			//print_p("deleted ".$index."");
			dbquery("DELETE FROM ".DB_FORUMS." ".(multilang_table("FO") ? "WHERE forum_language='".LANGUAGE."' AND" : "WHERE")." forum_id='".$index."' ".($time ? "AND forum_lastpost < '".$time."'" : '')." ");
		}
	}

	/**
	 * Recalculate a forum post count
	 * @param $forum_id
	 * @return string
	 */
	static function recalculate_post($forum_id) {
		global $locale;
		// update last post
		$result = dbquery("SELECT thread_lastpost, thread_lastuser FROM ".DB_FORUM_THREADS." WHERE forum_id='".$forum_id."' ORDER BY thread_lastpost DESC LIMIT 0,1"); // get last thread_lastpost.
		if (dbrows($result)) {
			$data = dbarray($result);
			$result = dbquery("UPDATE ".DB_FORUMS." SET forum_lastpost='".$data['thread_lastpost']."', forum_lastuser='".$data['thread_lastuser']."' WHERE forum_id='".$forum_id."'");
		} else {
			$result = dbquery("UPDATE ".DB_FORUMS." SET forum_lastpost='0', forum_lastuser='0' WHERE forum_id='".$forum_id."'");
		}
		// update postcount on each threads -  this is the remaining.
		$result = dbquery("SELECT COUNT(post_id) AS postcount, thread_id FROM ".DB_FORUM_POSTS." WHERE forum_id='".$forum_id."' GROUP BY thread_id");
		if (dbrows($result)) {
			while ($data = dbarray($result)) {
				dbquery("UPDATE ".DB_FORUM_THREADS." SET thread_postcount='".$data['postcount']."' WHERE thread_id='".$data['thread_id']."'");
			}
		}
		// calculate and update total combined postcount on all threads to forum
		$result = dbquery("SELECT SUM(thread_postcount) AS postcount, forum_id FROM ".DB_FORUM_THREADS."
			WHERE forum_id='".$forum_id."' GROUP BY forum_id");
		if (dbrows($result)) {
			while ($data = dbarray($result)) {
				dbquery("UPDATE ".DB_FORUMS." SET forum_postcount='".$data['postcount']."' WHERE forum_id='".$data['forum_id']."'");
			}
		}
		// calculate and update total threads to forum
		$result = dbquery("SELECT COUNT(thread_id) AS threadcount, forum_id FROM ".DB_FORUM_THREADS." WHERE forum_id='".$forum_id."' GROUP BY forum_id");
		if (dbrows($result)) {
			while ($data = dbarray($result)) {
				dbquery("UPDATE ".DB_FORUMS." SET forum_threadcount='".$data['threadcount']."' WHERE forum_id='".$data['forum_id']."'");
			}
		}
		return $locale['611'].mysql_affected_rows();
	}

	/**
	 * Recalculate users post count
	 * @param $forum_id
	 */
	static function prune_users_posts($forum_id) {
		// after clean up.
		$result = dbquery("SELECT post_user FROM ".DB_FORUM_POSTS." WHERE forum_id='".$forum_id."'");
		$user_data = array();
		if (dbrows($result)>0) {
			while ($data = dbarray($result)) {
				$user_data[$data['post_user']] = isset($user_data[$data['post_user']]) ? $user_data[$data['post_user']]+1 : 1;
			}
		}
		if (!empty($user_data)) {
			foreach($user_data as $user_id => $count) {
				$result = dbquery("SELECT user_post FROM ".DB_USERS." WHERE user_id='".$user_id."'");
				if (dbrows($result)>0) {
					$_userdata = dbarray($result);
					$calculated_post = $_userdata['user_post']-$count;
					$calculated_post = $calculated_post > 1 ? $calculated_post : 0;
					dbquery("UPDATE ".DB_USERS." SET user_post='".$calculated_post."' WHERE user_id='".$user_id."'");
				}
			}
		}
	}
}
