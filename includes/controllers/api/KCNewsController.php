<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_REST_Response;
use WP_REST_Server;
use WP_Query;
use WP_Post;

class KCNewsController extends KCBase {

    public $module = 'news';

    public $nameSpace;

    function __construct() {

        $this->nameSpace = KIVICARE_API_NAMESPACE;

        add_action( 'rest_api_init', function () {

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-news-detail', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'get_news_detail' ],
				'permission_callback' => '__return_true',
            ));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-news-list', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'get_news_list' ],
				'permission_callback' => '__return_true',
			));
        });

    }

    public function get_news_list ($request) {
        
        $parameters = $request->get_params();

		$args = [
			'post_type' 		=> 'post',
			'post_status' 		=> 'publish',
			'posts_per_page' 	=> (!empty($parameters['posts_per_page']) && isset($parameters['posts_per_page'])) ? $parameters['posts_per_page'] : 5,
            'paged' 			=> (!empty($parameters['paged']) && isset($parameters['paged'])) ? $parameters['paged'] : 1,
            's' 				=> (isset($parameters['search']) && $parameters['search'] != '' ) ? $parameters['search'] : ''

        ];

        $masterarray = [];    
        $wp_query = new WP_Query( $args );

		if ($wp_query->have_posts()) {
			while ($wp_query->have_posts()) {
				$wp_query->the_post();
				array_push($masterarray, get_news_data($wp_query));
            }

            $count_posts = wp_count_posts();
            $dashborad['total'] =  $count_posts->publish;
			$dashborad['data'] = $masterarray;
		} else {
			$count_posts = wp_count_posts();
			$dashborad['total'] =  $count_posts->publish;
			$dashborad['data'] = $masterarray;
		}

        return comman_custom_response($dashborad);
    }

    public function get_news_detail ($request) {

		global $post;
		global $wpdb;

		$parameters = $request->get_params();

		$data = kcaValidationToken();
        $user_id = null;
		if ($data['status']) {
			$user_id = $data['user_id'];
        }

		$post_id = (isset($parameters['post_id']) && $parameters['post_id'] != '' ) ? $parameters['post_id'] : null;
		$post_data = WP_Post::get_instance($post_id);

		if (empty($post_data)) {
			return comman_custom_response( (object) array());
		}
		$post_author = get_user_by('ID', $post_data->post_author);
		$post_data->post_content = esc_html(get_the_content(null,false,$post_data->ID));
		$post_data->post_author_name	= ($post_author !== null) ? $post_author->data->display_name : '' ;

        $full_image 			= wp_get_attachment_image_src( get_post_thumbnail_id( $post_data->ID  ), "full" );
        $post_data->image 			= !empty($full_image) ? $full_image[0] : null;
		$post_data->readable_date 	= get_the_date('', $post_data);
		$post_data->no_of_comments 	= get_comments_number_text(false, false, false, $post_data->ID);
		$post_data->share_url 		= get_the_permalink($post_data->ID);
		$post_data->category 		= get_the_category($post_data->ID);

		return comman_custom_response($post_data);
	}
}