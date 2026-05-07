<?php
if (!class_exists('SimpleGitHubUpdater')) :

	class SimpleGitHubUpdater {
		private $user = null,
				$repo = null,
				$slug = null,
				$tag  = null,
				$url  = null;

		/**
		 * Init the instance of the updater.
		 *
		 * Require this file and then create a new instance of the class. Example:
		 * new \SimpleGitHubUpdater('xxsimoxx', 'fx-builder-widgets', 'fx-builder-widgets/fx-builder-widgets.php');
		 *
		 * This class uses the tag of the release. Can be prefixed by the charachter 'v'.
		 *
		 * @param string  $user     GitHub user name.
		 * @param string  $repo     GitHub repository name.
		 * @param string  $slug     folder/file.php of the plugin or folder of the theme.
		 * @param string  $type     'plugin' or 'theme'. Default 'plugin'
		 * @param boolean $git_safe Do not run if there is a .git folder.
		 */
		public function __construct($user, $repo, $slug, $type = 'plugin', $git_safe = true) {
			if (is_dir(dirname(__DIR__).'/.git') && $git_safe) {
				return;
			}
			$this->user = $user;
			$this->repo = $repo;
			$this->slug = $slug;
			add_filter("update_{$type}s_github.com", [$this, 'update_uri_filter'], 10, 4);
		}

		public function update_uri_filter($update, $plugin_data, $plugin_file, $locales) {
			if ($plugin_file !== $this->slug) {
				return $update;
			}
			if (!$this->check_updates()) {
				return $update;
			}
			if (version_compare(ltrim($this->tag, 'v'), $plugin_data['Version']) !== 1) {
				return false;
			}
			return [
				'slug'         => $plugin_file,
				'version'      => ltrim($this->tag, 'v'),
				'package'      => $this->url,
			];
		}

		private function check_updates($force = false) {
			if (is_null($this->user) || is_null($this->repo)) {
				return false;
			}
			if (!is_null($this->tag) && !is_null($this->url)) {
				return true;
			}
			$stored = get_transient("githubupdater-{$this->user}_{$this->repo}");
			if ($stored !== false && !$force) {
				$this->tag = $stored['tag'];
				$this->url = $stored['url'];
				return true;
			}
			$url = "https://api.github.com/repos/{$this->user}/{$this->repo}/releases/latest";
			if (defined('GITHUB_API_TOKEN')) {
				$auth = [
					'headers' => [
						'Authorization' => 'token '.GITHUB_API_TOKEN,
					],
				];
			}
			$response = wp_safe_remote_get(esc_url_raw($url), $auth ?? []);
			$response_body = wp_remote_retrieve_body($response);
			$result = json_decode($response_body);
			if (!isset($result->tag_name) || !isset($result->assets[0]->browser_download_url)) {
				return false;
			}
			$this->tag = $result->tag_name;
			$this->url = $result->assets[0]->browser_download_url;
			set_transient(
				"githubupdater-{$this->user}_{$this->repo}",
				[
					'tag' => $this->tag,
					'url' => $this->url,
				],
				DAY_IN_SECONDS
			);
		}
	}

endif;
