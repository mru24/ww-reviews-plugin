<?php

/**
 * @package WW Reviews Plugin
 *
 */

 /*
  * Plugin Name: WW Reviews Plugin
  * Plugin URI:
  * Description: WordPress plugin for managing customer reviews with star ratings, custom templates, and email invitations.
  * Version: 3.1.2
  * Author: Val Wroblewski
  * Author URI:
  * Licence: GPLv2 or later
  * Text-Domain: ww-reviews-domain
  * Domain Path: /languages
  * Requires at least: 5.0
  * Requires PHP: 7.2
  */

if( !defined( 'ABSPATH' )) { die; }

define( 'PLUGIN_DIR', dirname(__FILE__).'/' );

require_once 'includes/ReviewMetaBoxClass.php';

class WWReviewsPlugin {
	public $plugin;
  public $plugin_version="3.1.2";
	public $plugin_file;
	public $plugin_nice_name;
	public $textDomain;
	public $custom_post;
	public $admin_page;

	// SETTINGS
	public $settings;
	public $settings_group;
	public $email;

	function __construct() {
		$this->plugin = 'ww_reviews';
		$this->plugin_file = plugin_basename( __FILE__ );
		$this->plugin_nice_name = 'WW Reviews';
		$this->textDomain = $this->plugin.'_domain';
		$this->admin_page = $this->plugin.'_admin';
		$this->custom_post = $this->plugin.'_cp';

		// SETTINGS
		$this->settings = $this->plugin.'_settings';
		$this->settings_group = $this->plugin.'_settings_group';
	}
	function register() {

		// REGISTER CUSTOM POST
		add_action( 'init', array( $this, 'register_cp1' ));

		// REGISTER SCRIPTS & STYLES
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ));
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ));
		add_action( 'admin_menu', array( $this, 'add_submenu_page_to_post_type' ) );

		// PLUGINS PAGE SETTINGS LINK
		add_filter( "plugin_action_links_$this->plugin_file", array( $this, 'plugins_page_settings_link' ));

    add_filter('option_page_capability_' . $this->settings_group, function() {
      return 'edit_posts';
    });
		// REGISTER SETTING
		add_action('admin_init', array($this, 'register_settings'));

		// REGISTER SHORTCODES
		add_action('init', array($this, 'register_shortcodes'));

		// AJAX
		add_action('wp_ajax_sendReviewEmail', array($this,'sendReviewEmail'));

    add_action('wp_ajax_submit_user_review', array($this, 'handle_review_submission'));
    add_action('wp_ajax_nopriv_submit_user_review', array($this, 'handle_review_submission'));
    add_action('wp_ajax_load_more_reviews', array($this, 'handle_load_more_reviews'));
    add_action('wp_ajax_nopriv_load_more_reviews', array($this, 'handle_load_more_reviews'));

    add_action('admin_enqueue_scripts', function() {
      wp_localize_script('ww-scripts', 'ww_auth', array(
        'nonce' => wp_create_nonce('ww_reviews_nonce')
      ));
    });

    // COLOR PICKER
    add_action('admin_footer', [$this, 'admin_footer_script']);
	}
	function activate() {
		flush_rewrite_rules();
	}
	function deactivate () {
		flush_rewrite_rules();
	}
	function uninstall() {

	}
	function register_cp1() {
		register_post_type($this->custom_post,
			array(
				'public' => true,
				'labels' => array(
					'name' => __($this->plugin_nice_name,$this->textDomain),
					'singular_name' => __($this->plugin_nice_name,$this->textDomain),
				),
				'has_archive'	=> true,
				'rewrite'	=>	array('slug' => 'review'),
				'menu_position' => 5,
				'menu_icon' => 'dashicons-format-quote',
				'supports' => array(
					'title', 'editor', 'custom-fields','excerpt','thumbnail',
				),
			)
		);
	}
	function enqueue_admin_scripts() {
		wp_enqueue_style( 'ww-styles', plugins_url( '/assets/styles-admin.css', __FILE__ ) );
    wp_enqueue_script('ww-scripts', plugins_url('/assets/main-admin.js', __FILE__), array('jquery'), $this->plugin_version, true);
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
	}
	function enqueue_frontend_scripts() {
		wp_enqueue_style( 'ww-styles', plugins_url( '/assets/styles.css', __FILE__ ) );
		wp_enqueue_script( 'ww-scripts', plugins_url( '/assets/main.js', __FILE__ ), array('jquery'), $this->plugin_version, true);
	}
	public function add_submenu_page_to_post_type() {
    add_submenu_page(
      'edit.php?post_type='.$this->custom_post,
      __('Settings', $this->textDomain),
      __('Settings', $this->textDomain),
      'edit_posts',
      $this->admin_page,
      array($this, 'admin_page_display'));
	}
  public function admin_page_display() {
    if (!current_user_can('edit_posts')) {
      wp_die('Unauthorized user');
    }
    require_once 'templates/settings.php';
  }
	public function plugins_page_settings_link( $links ) {
		$settings_link = '<a href="edit.php?post_type='.$this->custom_post.'&page='.$this->admin_page.'">Settings</a>';
		array_push( $links, $settings_link );
		return $links;
	}
	public function register_settings() {
		add_settings_section('ww_first_admin_section', null, null, $this->admin_page);
		$items = array(
      ['name'=>'_spacer_1', 'method'=>'spacerHtml', 'label'=>'<h3>Email Template</h3>'],
			['name'=>'_email_subject', 'method'=>'emailSubjectHtml', 'label'=>'Email subject'],
			['name'=>'_email_template', 'method'=>'emailTemplateHtml', 'label'=>'Email template'],
      ['name'=>'_spacer_2', 'method'=>'spacerHtml', 'label'=>'<h3>Reviews List</h3>'],
			['name'=>'_list_template', 'method'=>'listTemplateHtml', 'label'=>'List template'],
			['name'=>'_list_template_styles', 'method'=>'listTemplateStylesHtml', 'label'=>'List template styles'],
			['name'=>'_list_template_fields', 'method'=>'listTemplateFieldsHtml', 'label'=>'List template fields'],
      ['name'=>'_reviews_per_page', 'method'=>'reviewsPerPageHtml', 'label'=>'Reviews Per Page'],
      ['name'=>'_enable_load_more', 'method'=>'enableLoadMoreHtml', 'label'=>'Enable Load More Button'],
      ['name'=>'_load_more_text', 'method'=>'loadMoreTextHtml', 'label'=>'Load More Button Text'],
      ['name'=>'_star_color', 'method'=>'starColorPicker', 'label'=>'Review Star Colour'],
      ['name'=>'_spacer_3', 'method'=>'spacerHtml', 'label'=>'<h3>Submit Review Form</h3>'],
      ['name'=>'_submit_form_styles', 'method'=>'submitFormStylesHtml', 'label'=>'Submit Form styles'],
      ['name'=>'_spacer_4', 'method'=>'spacerHtml', 'label'=>'<h3>Shortcodes</h3>'],
			['name'=>'_list_shortcode', 'method'=>'listShortcodeHtml', 'label'=>'List shortcode'],
			['name'=>'_form_shortcode', 'method'=>'submitFormShortcodeHtml', 'label'=>'Submit Form shortcode'],
		);
		foreach ($items as $item) {
      add_settings_field($this->plugin.$item['name'], $item['label'], array($this, $item['method']), $this->admin_page, 'ww_first_admin_section');
      if (!empty($item['name'])) {
        register_setting(
          $this->settings_group,
          $this->plugin.$item['name'],
          array(
            'sanitize_callback' => null
          )
		    );
      }
    }
	}
  public function spacerHtml() {
    echo '<hr style="margin: 10px 0; border: 0; border-top: 2px solid #ccc;" />';
  }
	public function emailSubjectHtml() { ?>
		<input type="text" name="<?php echo $this->plugin.'_email_subject'; ?>" value="<?php echo get_option($this->plugin.'_email_subject') ?>" style="width:80%;" />
  <?php
	}
  public function emailTemplateHtml() {
  	$option_name = $this->plugin . '_email_template';
    $content = get_option($option_name);
    if (empty($content)) {
      $template_path = plugin_dir_path(__FILE__) . 'templates/email-template.html';
      if (file_exists($template_path)) {
        $content = file_get_contents($template_path);
      }
    }
    $settings = array(
      'teeny' => false,
        'textarea_rows' => 15,
        'tabindex' => 1
    );
    wp_editor(stripslashes($content),$option_name,$settings);
  }
	public function listTemplateHtml() {
    $option_name = $this->plugin . '_list_template';
    $content = get_option($option_name);
    if (empty($content)) {
      $template_path = plugin_dir_path(__FILE__) . 'templates/list-template.html';
      if (file_exists($template_path)) {
        $content = file_get_contents($template_path);
      }
    } ?>
    <textarea
        name="<?php echo esc_attr($option_name); ?>"
        style="width:100%;height:350px;resize:none;"
        <?php if (!current_user_can('manage_options')) echo "readonly"; ?>
    ><?php echo esc_textarea($content); ?></textarea>
  <?php
  }
	public function listTemplateStylesHtml() {
    $option_name = $this->plugin . '_list_template_styles';
    $content = get_option($option_name);
    if (empty($content)) {
      $template_path = plugin_dir_path(__FILE__) . 'templates/list-template-styles.css';
      if (file_exists($template_path)) {
        $content = file_get_contents($template_path);
      }
    } ?>
		<textarea name="<?php echo esc_attr($option_name); ?>" style="width:100%;height:200px;resize:none;" <?php if(!current_user_can('manage_options')) echo "readonly"; ?>><?php echo esc_textarea($content); ?></textarea>
  <?php
	}
	public function listTemplateFieldsHtml() { ?>
		<textarea style="width:100%;height:80px;resize:none;font-size: 19px;" readonly>[name] [info] [excerpt] [content] [date] [stars]</textarea>
  <?php
	}
  public function reviewsPerPageHtml() { ?>
    <input type="number"
           name="<?php echo $this->plugin.'_reviews_per_page'; ?>"
           value="<?php echo esc_attr(get_option($this->plugin.'_reviews_per_page', 5)); ?>"
           min="1"
           max="50"
           style="width: 100px;" />
    <p class="description">Number of reviews to display initially</p>
  <?php
  }
  public function enableLoadMoreHtml() { ?>
    <input type="checkbox"
           name="<?php echo $this->plugin.'_enable_load_more'; ?>"
           value="1"
           <?php checked(1, get_option($this->plugin.'_enable_load_more', 1)); ?> />
    <p class="description">Show "Load More" button to load additional reviews</p>
  <?php
  }
  public function loadMoreTextHtml() { ?>
    <input type="text"
           name="<?php echo $this->plugin.'_load_more_text'; ?>"
           value="<?php echo esc_attr(get_option($this->plugin.'_load_more_text', 'Load More Reviews')); ?>"
           style="width: 200px;" />
    <p class="description">Text displayed on the load more button</p>
  <?php
  }
  public function listShortcodeHtml() { ?>
		<textarea style="width:100%;height:50px;resize:vertical;" readonly><?php echo '[ww_reviews]'; ?></textarea>
  <?php
	}
  public function submitFormStylesHtml() {
    $option_name = $this->plugin . '_submit_form_styles';
    $content = get_option($option_name);
    if (empty($content)) {
      $template_path = plugin_dir_path(__FILE__) . 'templates/form-styles.css';
      if (file_exists($template_path)) {
        $content = file_get_contents($template_path);
      }
    } ?>
		<textarea name="<?php echo esc_attr($option_name); ?>" style="width:100%;height:200px;resize:none;" <?php if(!current_user_can('manage_options')) echo "readonly"; ?>><?php echo esc_textarea($content); ?></textarea>
  <?php
	}
  public function submitFormShortcodeHtml() { ?>
		<textarea style="width:100%;height:50px;resize:vertical;" readonly><?php echo '[ww_review_form]'; ?></textarea>
  <?php
	}
  public function starColorPicker() { ?>
    <input type="text"
        name="<?php echo $this->plugin.'_star_color'; ?>"
        value="<?php echo esc_attr(get_option($this->plugin.'_star_color')); ?>"
        class="my-color-field"
        data-default-color="#FFCC00" />
  <?php
  }
  public function admin_footer_script() { ?>
    <script>
      // COLOR PICKER
      jQuery(document).ready(function($){
        $('.my-color-field').wpColorPicker();
      });
    </script>
  <?php
  }

	//SHORTCODES
	public function register_shortcodes() {
		add_shortcode( 'ww_reviews', array($this, 'create_wp_query_shortcode' ));
    add_shortcode('ww_review_form', array($this, 'render_review_form'));
	}
	public function create_wp_query_shortcode($atts) {
    $atts = shortcode_atts(array(
      'per_page' => get_option($this->plugin.'_reviews_per_page', 5),
      'load_more' => get_option($this->plugin.'_enable_load_more', 1),
      'button_text' => get_option($this->plugin.'_load_more_text', 'Load More Reviews'),
    ), $atts, 'ww_reviews');

    $unique_id = 'ww-reviews-' . uniqid();
    $per_page = intval($atts['per_page']);
    $enable_load_more = $atts['load_more'] == '1' || $atts['load_more'] === true;

    $total_count = new WP_Query(array(
      'post_type' => $this->custom_post,
      'meta_query' => array(
        array(
          'key' => 'ww_review_active',
          'value' => '1',
          'type' => 'NUMERIC'
        )
      ),
      'posts_per_page' => -1,
      'fields' => 'ids'
    ));
    $total_reviews = $total_count->found_posts;
    wp_reset_postdata();

    $loop = new WP_Query(array(
      'post_type' => $this->custom_post,
      'meta_query' => array(
        array(
          'key' => 'ww_review_active',
          'value' => '1',
          'type' => 'NUMERIC'
        )
      ),
      'orderby' => 'post_id',
      'order' => 'DESC',
      'posts_per_page' => $per_page,
      'paged' => 1,
    ));
    $reviews_html = $this->generate_reviews_html($loop);

    $output = '<div id="' . $unique_id . '" class="ww-reviews-wrapper"
                data-total="' . $total_reviews . '"
                data-per-page="' . $per_page . '"
                data-loaded="' . $per_page . '"
                data-paged="1">';
    $output .= '<div class="ww-reviews-container">';
    $output .= $reviews_html;
    $output .= '</div>';

    if ($enable_load_more && $total_reviews > $per_page) {
      $output .= '<div class="ww-load-more-container">';
      $output .= '<button type="button" class="ww-load-more-btn" data-page="2">'
                  . esc_html($atts['button_text']) . '</button>';
      $output .= '<div class="ww-loading-spinner" style="display:none;">Loading...</div>';
      $output .= '</div>';
    }

    $output .= '</div>';

    $raw_css = get_option($this->plugin.'_list_template_styles');
    $scoped_css = $this->scope_css($raw_css, $unique_id);

    $load_more_css = '
        .ww-load-more-container {
            text-align: center;
            margin: 30px 0;
        }
        .ww-load-more-btn {
            background: #0073aa;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s ease;
        }
        .ww-load-more-btn:hover {
            background: #005a87;
        }
        .ww-load-more-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .ww-loading-spinner {
            display: inline-block;
            margin-left: 10px;
            color: #666;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .ww-loading-spinner::before {
            content: "";
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-right: 8px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #0073aa;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    ';

    $style_tag = '<style>' . $scoped_css . $load_more_css . '</style>';
    $script_tag = $this->get_load_more_script($unique_id);
    return $style_tag . $output . $script_tag;
  }
  private function generate_reviews_html($loop) {
    $reviews_html = '';
    $raw_template = get_option($this->plugin.'_list_template');
    $starColor = esc_attr(get_option($this->plugin.'_star_color', '#FFCC00'));

    while ($loop->have_posts()) {
      $loop->the_post();
      $post_id = get_the_ID();

      $status = get_post_meta($post_id, 'ww_review_active', true);
      if (!$status) continue;

      $name = esc_html(strip_tags(get_the_title()));
      $content = wp_kses_post(get_the_content());
      $excerpt = esc_html(strip_tags(get_the_excerpt()));
      $raw_info = esc_html(strip_tags(get_post_meta($post_id, 'ww_review_info', true)));
      $raw_date = get_post_meta($post_id, 'ww_review_date', true);
      $date = $raw_date ? explode('-', strip_tags($raw_date)) : ['00', '00', '0000'];
      $dateReady = isset($date[2]) ? $date[2].'/'.$date[1].'/'.$date[0] : '';
      $stars = intval(strip_tags(get_post_meta($post_id, 'ww_review_stars', true)));

      $info = !empty($raw_info) ? '<span class="ww-review-info"> - ' . $raw_info . '</span>' : '';

      $starsHtml = '<span class="ww-review-stars-container">';
      for ($i = 1; $i <= 5; $i++) {
          $color = ($i <= $stars) ? $starColor : '#cccccc';
          $starsHtml .= '<span style="color:' . $color . '; font-size: 25px;">&#9733;</span>';
      }
      $starsHtml .= '</span>';

      $templateData = array(
        'name'    => $name,
        'content' => $content,
        'excerpt' => $excerpt,
        'info'    => $info,
        'date'    => $dateReady,
        'stars'   => $starsHtml,
      );

      $text = $raw_template;
      foreach ($templateData as $key => $value) {
        $text = str_replace("[$key]", $value, $text);
      }
      $reviews_html .= $text;
    }
    wp_reset_postdata();

    return $reviews_html;
  }
  private function scope_css($css, $scope_id) {
    if (empty($css)) return '';

    $scoped_css = preg_replace_callback(
      '/@media[^{]+\{([\s\S]+?\})\s*\}/',
      function ($matches) use ($scope_id) {
          $inner = preg_replace(
              '/([^\r\n,{}]+)(?=[^{}]*\{)/',
              '#' . $scope_id . ' $1',
              $matches[1]
          );
          return str_replace($matches[1], $inner, $matches[0]);
      },
      $css
    );

    $scoped_css = preg_replace(
      '/(^|})([^{@}]+){/',
      '$1#' . $scope_id . ' $2{',
      $scoped_css
    );

    return $scoped_css;
  }
  private function get_load_more_script($unique_id) {
    ob_start();
    ?>
    <script>
    (function($) {
      $(document).ready(function() {
        var container = $('#<?php echo $unique_id; ?>');
        var loadMoreBtn = container.find('.ww-load-more-btn');
        var spinner = container.find('.ww-loading-spinner');
        var reviewsContainer = container.find('.ww-reviews-container');
        var currentPage = parseInt(container.data('paged')) || 1;
        var perPage = parseInt(container.data('per-page')) || 5;
        var totalReviews = parseInt(container.data('total')) || 0;
        var loadedCount = parseInt(container.data('loaded')) || perPage;
        var isLoading = false;

        if (loadMoreBtn.length === 0) return;

        loadMoreBtn.on('click', function() {
          if (isLoading) return;

          var nextPage = currentPage + 1;
          var startIndex = loadedCount;
          if (loadedCount >= totalReviews) {
            loadMoreBtn.prop('disabled', true).text('No more reviews');
            return;
          }

          isLoading = true;
          loadMoreBtn.prop('disabled', true);
          spinner.show();

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'load_more_reviews',
                        page: nextPage,
                        per_page: perPage,
                        nonce: '<?php echo wp_create_nonce('ww_load_more_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            reviewsContainer.append(response.data.html);
                            currentPage = nextPage;
                            loadedCount += response.data.count;
                            container.data('paged', currentPage);
                            container.data('loaded', loadedCount);

                            if (loadedCount >= totalReviews) {
                                loadMoreBtn.prop('disabled', true).text('No more reviews');
                            } else {
                                loadMoreBtn.prop('disabled', false);
                            }
                        } else {
                            console.error('Error loading reviews:', response.data);
                            loadMoreBtn.prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', error);
                        loadMoreBtn.prop('disabled', false);
                        alert('Error loading reviews. Please try again.');
                    },
                    complete: function() {
                        isLoading = false;
                        spinner.hide();
                    }
                });
            });
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
  }
  public function handle_load_more_reviews() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ww_load_more_nonce')) {
      wp_send_json_error('Security check failed');
      wp_die();
    }

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 5;

    if ($page < 1) $page = 1;
    if ($per_page < 1) $per_page = 5;

    $loop = new WP_Query(array(
      'post_type' => $this->custom_post,
      'meta_query' => array(
          array(
              'key' => 'ww_review_active',
              'value' => '1',
              'type' => 'NUMERIC'
          )
      ),
      'orderby' => 'post_id',
      'order' => 'DESC',
      'posts_per_page' => $per_page,
      'paged' => $page,
    ));

    $html = $this->generate_reviews_html($loop);
    $count = $loop->post_count;

    wp_send_json_success(array(
      'html' => $html,
      'count' => $count,
      'page' => $page
    ));
    wp_die();
  }
  public function render_review_form() {
    $unique_id = 'ww-form-' . uniqid();
    $raw_css   = get_option($this->plugin . '_submit_form_styles');

    ob_start(); ?>
    <div id="<?php echo $unique_id; ?>" class="ww-review-form-container">
      <form id="ww-review-form" class="ww-submission-form">
        <?php wp_nonce_field('ww_review_submit_nonce', 'ww_nonce'); ?>

        <div class="form-group">
          <label>Name *</label>
          <input type="text" name="reviewer_name" required>
        </div>

        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="reviewer_email" required>
        </div>

        <div class="form-group">
          <label for="reviewer_tel">Telephone (Optional)</label>
          <input type="tel"
                name="reviewer_tel"
                id="reviewer_tel"
                pattern="[0-9+\-\s\(\)]+"
                placeholder="e.g., 07700 900123 or +44 7700 900123"
                title="Please enter a valid phone number"
                value="">
          <!-- <small class="form-text text-muted">Optional - only used to contact you about your review</small> -->
        </div>

        <div class="form-group">
          <label>Rating *</label>
          <div class="ww-star-rating">
            <?php
            for ($i = 5; $i >= 1; $i--):
              $star_id = "star-{$unique_id}-{$i}";
            ?>
              <input type="radio" id="<?php echo $star_id; ?>" name="reviewer_stars" value="<?php echo $i; ?>" />
              <label for="<?php echo $star_id; ?>" title="<?php echo $i; ?> stars">&#9733;</label>
            <?php endfor; ?>
          </div>
        </div>

        <div class="form-group">
          <label>Review *</label>
          <textarea name="reviewer_content" required></textarea>
        </div>

        <div class="form-group">
          <label>Extra Info (e.g. Location)</label>
          <input type="text" name="reviewer_info">
        </div>
        <div class="form-group" style="margin-top:-20px;">
          <small>* fields required</small>
        </div>

        <button type="submit" class="ww-submit-btn btn btn-primary">Submit Review</button>
        <div id="form-message"></div>
      </form>

      <style>
        #<?php echo $unique_id; ?> button[type="submit"] {
          border: none;
          padding: 10px 21px;
          border-radius: 5px;
          margin: 5px 15px 15px;
          transition: 0.5s;
          cursor: pointer;
        }
        #<?php echo $unique_id; ?> button[type="submit"]:hover {
          filter: brightness(0.8);
        }
        #<?php echo $unique_id; ?> .ww-star-rating {
          display: flex;
          flex-direction: row-reverse;
          justify-content: flex-end;
        }
        #<?php echo $unique_id; ?> .ww-star-rating input { display: none !important; }
        #<?php echo $unique_id; ?> .ww-star-rating label {
          font-size: 32px;
          color: #ccc;
          cursor: pointer;
          padding: 0 2px;
          transition: color 0.2s ease-in-out;
        }
        #<?php echo $unique_id; ?> .ww-star-rating label:hover,
        #<?php echo $unique_id; ?> .ww-star-rating label:hover ~ label,
        #<?php echo $unique_id; ?> .ww-star-rating input:checked ~ label {
          color: #FFCC00 !important;
        }
        #<?php echo $unique_id; ?> small {
          font-size: 14px;
        }
        <?php echo $raw_css; ?>
      </style>

      <script>
        jQuery(document).ready(function($) {
          var $wrapper = $('#<?php echo $unique_id; ?>');
          var $form = $wrapper.find('#ww-review-form');

          $form.on('submit', function(e) {
            e.preventDefault();
              var rating = $form.find("input[name='reviewer_stars']:checked").val();
              if (!rating) {
                alert('Please select a star rating!');
                return;
              }
              var formData = $form.serialize();
              $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData + '&action=submit_user_review',
                beforeSend: function() {
                  $form.find('.ww-submit-btn').prop('disabled', true).text('Processing...');
                },
                success: function(response) {
                  if(response.success) {
                    $form.find('#form-message').html('<p style="color:green; font-weight:bold;">' + response.data + '</p>');
                    $form[0].reset();
                  } else {
                    $form.find('#form-message').html('<p style="color:red;">' + response.data + '</p>');
                  }
                },
                error: function() {
                  $form.find('#form-message').html('<p style="color:red;">Connection error. Try again.</p>');
                },
                complete: function() {
                  $form.find('.ww-submit-btn').prop('disabled', false).text('Submit Review');
                }
              });
          });
        });
      </script>
    </div>
    <?php
    return ob_get_clean();
  }
  public function handle_review_submission() {
    check_ajax_referer('ww_review_submit_nonce', 'ww_nonce');

    $name    = sanitize_text_field($_POST['reviewer_name']);
    $email   = sanitize_email($_POST['reviewer_email']);
    $tel = $this->sanitize_telephone($_POST['reviewer_tel']);
    $content = sanitize_textarea_field($_POST['reviewer_content']);
    $stars   = intval($_POST['reviewer_stars']);
    if ($stars < 1 || $stars > 5) {
      wp_send_json_error('Invalid rating');
    }
    $info    = sanitize_text_field($_POST['reviewer_info']);
    $post_id = wp_insert_post(array(
      'post_title'   => $name,
      'post_content' => $content,
      'post_status'  => 'pending',
      'post_type'    => $this->custom_post,
    ));
    if ($post_id) {
      update_post_meta($post_id, 'ww_review_stars', $stars);
      update_post_meta($post_id, 'ww_review_tel', $tel);
      update_post_meta($post_id, 'ww_review_email', $email);
      update_post_meta($post_id, 'ww_review_info', $info);
      update_post_meta($post_id, 'ww_review_date', date('Y-m-d'));
      update_post_meta($post_id, 'ww_review_active', 0);
      wp_send_json_success('Thank you! Your review is awaiting approval.');
    } else {
      wp_send_json_error('Could not save review.');
    }
  }
  private function sanitize_telephone($telephone) {
    $cleaned = preg_replace('/[^0-9+\-\s\(\)\.]/', '', $telephone);
    $cleaned = trim($cleaned);
    if (strlen($cleaned) > 20) {
      $cleaned = substr($cleaned, 0, 20);
    }
    return $cleaned;
  }
	public function sendReviewEmail() {
		if ( ! isset($_POST['_wpnonce']) || ! wp_verify_nonce($_POST['_wpnonce'], 'send_review_email') ) {
      wp_die('Security check failed');
    }
    if ( ! current_user_can('edit_posts') ) {
      wp_die('Unauthorized');
    }
    $to = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $postID = isset($_POST['id']) ? absint($_POST['id']) : 0;

    if ( empty($to) || ! is_email($to) || $postID <= 0 ) {
      return;
    }

    $date = date('d.m.Y');
    $name = get_option('blogname');
    $email = get_option('admin_email');
    $subject = sanitize_text_field(get_option($this->plugin.'_email_subject'));

    $message = wp_kses_post(wpautop(get_option($this->plugin.'_email_template')));

    $headers = array(
      'Content-type: text/html; charset=utf-8',
      'From: ' . $name . ' <' . $email . '>'
    );

    $sent = wp_mail($to, $subject, $message, $headers);

    if ( $sent ) {
      update_post_meta($postID, 'ww_review_email_sent', $date);
    } else {
      error_log('Review email failed for post ID: ' . $postID);
    }
  }
  public function load_textdomain() {
    load_plugin_textdomain(
      'ww-reviews',
      false,
      dirname(plugin_basename(__FILE__)) . '/languages/'
    );
  }
}

if( class_exists('WWReviewsPlugin')) {
	$wwReview = new WWReviewsPlugin();
	$wwReview->register();
}

// activation
register_activation_hook( __FILE__, array($wwReview,'activate') );

// deactivation
register_deactivation_hook(__FILE__, array($wwReview, 'deactivate'));

// uninstall

