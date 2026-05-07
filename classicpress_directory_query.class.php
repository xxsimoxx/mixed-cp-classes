<?php
class ClassicPressDirectoryQuery {

	private $force,
	        $per_page,
	        $cache_expiration;

	const PREFIX = 'cpdir_tools';

	public function __construct( $args ) {
		$this->force            = $args['force']            ?? false;
		$this->per_page         = $args['per_page']         ?? 10;
		$this->cache_expiration = $args['cache_expiration'] ?? DAY_IN_SECONDS;
	}

	private function query_dir( $args, $type = 'plugins' ) {
		if ( ! in_array( $type, ['plugins', 'themes'] ) ) {
			return false;
		}

		$signature = md5( $type . serialize( $args ) );
		$saved = get_transient( self::PREFIX . '_q_' . $signature );
		if ( $this->force !== true && $saved !== false ) {
			return $saved;
		}

		$query    = add_query_arg( $args, 'https://directory.classicpress.net/wp-json/wp/v2/' . $type . '?per_page=' . $this->per_page . '&page=1' );
		$response = wp_remote_get( $query );
		if ( is_wp_error ( $response ) || wp_remote_retrieve_response_code($response) !== 200) {
			return false;
		}

		$headers = wp_remote_retrieve_headers( $response );
		$pages   = (int) $headers['x-wp-totalpages'];
		$posts   = json_decode( wp_remote_retrieve_body( $response ), true);
		for ($i = 2; $i <= $pages; $i++) {
			$query    = add_query_arg( ['page' => $i] , remove_query_arg( 'page', $query ) );
			$response = wp_remote_get( $query );
			$posts    = array_merge( $posts, json_decode( wp_remote_retrieve_body( $response ), true ) );
		}
		set_transient( self::PREFIX . '_q_' . $signature, $posts, $this->cache_expiration );
		return $posts;
	}

	public function info_by_author( $author_id, $type = 'plugins' ) {
		$posts = $this->query_dir( ['author' => $author_id], $type );
		if ( $posts === false ) {
			return false;
		}
		$result = [];
		foreach ( $posts as $post ) {
			$result[] = [
				'title'         => $post['title']['rendered'],
				'version'       => $post['meta']['current_version'],
				'installations' => $post['meta']['active_installations'],
				'cpcs_ok'       => $post['meta']['cpcs_status'] === 'passing',
			];
		}
		return $result;
	}

	public function info_by_slug( $slugs, $type = 'plugins' ) {
		if ( is_array( $slugs ) ) {
			$slugs = implode( ',', $slugs );
		}
		$posts = $this->query_dir( ['byslug' => $slugs], $type );
		if ( $posts === false ) {
			return false;
		}
		$result = [];
		foreach ( $posts as $post ) {
			$result[] = [
				'title'         => $post['title']['rendered'],
				'version'       => $post['meta']['current_version'],
				'installations' => $post['meta']['active_installations'],
				'cpcs_ok'       => $post['meta']['cpcs_status'] === 'passing',
			];
		}
		return $result;
	}

}

