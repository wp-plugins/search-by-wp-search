<?php

class WPSearchPlugin {

    private $wpsearchDbVersion = "1.0";
    private $wpsearchIndexer;
    private $wpsearchApiClient;
    private $wpsearchSearch;
    private $results;
    private $searchParams;
    private $postsAssoc;
    private $searchSuccessful = false;
    private $facetsRender = array();
    private $resultsRender = array();

    public function __construct() {
        $this->wpsearchIndexer = new WPSearchIndexer();
        $this->wpsearchApiClient = new WPSearchApiClient();
        $this->wpsearchSearch = new WPSearchSearch();
    }

    public function run() {
        //register_activation_hook( __FILE__, array($this, 'install') );
        add_action('plugins_loaded', array($this, 'install'));
        $this->addWordpressActions();
    }

    public function install() {
        global $wpdb;
        /* $installedVersion = get_option("wpsearch_db_version");
          if ($installedVersion !== $this->wpsearchDbVersion) {
          $wpsearchLogTable = $wpdb->prefix . 'wpsearch_log';

          $charset_collate = $wpdb->get_charset_collate();

          $sql = "CREATE TABLE $wpsearchLogTable (
          ID bigint(20) unsigned NOT NULL auto_increment,
          log_date datetime NOT NULL default '0000-00-00 00:00:00',
          error_message longtext NOT NULL,
          params longtext NOT NULL,
          url varchar(100) default '' NOT NULL,
          PRIMARY KEY  (ID)
          ) $charset_collate;";

          require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
          dbDelta($sql);
          update_option('wpsearch_db_version', $this->wpsearchDbVersion);
          } */
    }

    public function addWordpressActions() {

        if (WPSearchUtils::isActive()) {
            $wordpressIndexer = $this->getWPSearchIndexer();
            $options = WPSearchOptions::getOptions();
            //post
            add_action('save_post', array($wordpressIndexer, 'handleSave'), 99);
            //add_action('untrashed_post', array($wordpressIndexer, 'handleSave'));
            //add_action('set_object_terms', array($wordpressIndexer, 'handleSave')); //called also on term delete event
            add_action('edit_post', array($wordpressIndexer, 'handleStatusChange'));
            add_action('before_delete_post', array($wordpressIndexer, 'handleDelete'));
            add_action('wp_trash_post', array($wordpressIndexer, 'handleDelete'));

            //term
            add_action('created_term', array($wordpressIndexer, 'handleTermSave'), 10, 3);
            add_action('edited_term', array($wordpressIndexer, 'handleTermSave'), 10, 3);
            add_action('delete_term', array($wordpressIndexer, 'handleTermDelete'), 10, 3);
            add_action('edited_term_taxonomies', array($wordpressIndexer, 'handleTermTaxonomyEdited'));


            add_action('wp_enqueue_scripts', array($this, 'enqueueScripts'));

            //shortcode
            add_shortcode('wpsearch', array($this, 'doShortcode'));

            //widget
            add_action('widgets_init', function() {
                register_widget('WPSearchWidget');
            });


            //search page
            if ($options['search_page_behavior'] == "use_existing" && !is_admin()) {

                add_action('pre_get_posts', array($this, 'actionPreGetPosts'));
                add_filter('posts_search', array($this, 'filterClearSearchString'));
                add_filter('post_limits', array($this, 'filterPostLimits'));

                add_filter('the_posts', array($this, 'filterThePosts'));
                // add_filter('found_posts', array($this, 'filterFoundPosts'));
            } else {
                add_action('template_redirect', array($this, 'templateRedirect'), 1);
            }
            add_filter('parse_query', array($this, 'filterParseQuery'));
            add_filter('the_post', array($this, 'filterThePost'));
        }
        add_action('admin_notices', array($this, 'displayNotice'));

        //ajax search
        add_action("wp_ajax_wpsearch_admin_requests", array($this, "handleAdminRequests"));
    }

    public function displayNotice() {
        $error = get_option("wpsearch_status_error");
        if ($error || !WPSearchUtils::isActive()) {
            ?>
            <style>
                .alert-critical{
                    border: 1px solid #e5e5e5;
                    padding: 0.4em 1em 1.4em 1em;
                    border-radius: 3px;
                    -webkit-border-radius: 3px;
                    border-width: 1px;
                    border-style: solid;
                    background-color: #993300;
                }
                .alert h3{
                    color: #fff;
                    margin: 1em 0 0.5em 0;

                }
                .alert p.description {
                    color: #fff;
                    font-size: 14px;
                    margin: 0 0;
                    font-style: normal;
                }
                .alert a{
                    color: #fff;
                }
            </style>
            <?php
            if (!WPSearchUtils::isActive()) {
                ?>
                <div class=" alert alert-critical">
                    <h3 class=""><?php esc_html_e('Please enter your key to start using WP Search.', 'wpsearch'); ?></h3>
                    <p class="description"><?php printf(__('Get your key at  <a href="%s" target="_blank">WP Search</a>', 'wpsearch'), 'http://wpsear.ch/'); ?></p>
                </div>
                <?php
            } elseif ($error == "key-invalid") {
                ?>
                <div class="alert alert-critical">
                    <h3 class=""><?php esc_html_e('There is a problem with your key.', 'wpsearch'); ?></h3>
                    <p class="description"><?php printf(__('Please contact <a href="%s" target="_blank">WP Search support</a> for assistance.', 'wpsearch'), 'http://wpsear.ch/contact/'); ?></p>
                </div>
                <?php
            } elseif ($error == "payment-invalid") {
                ?>
                <div class="alert alert-critical">
                    <h3 class=""><?php esc_html_e("Please update your payment details.", 'wpsearch'); ?></h3>
                    <p class="description"><?php printf(__('We cannot process your transaction. Please contact your bank for assistance, and <a href="%s" target="_blank">update your payment details</a>.', 'wpsearch'), 'http://wpsear.ch/account/'); ?></p>
                </div>
                <?php
            } elseif ($error == "cancelled") {
                ?>
                <div class="alert alert-critical">
                    <h3 class=""><?php esc_html_e("Your subscription is cancelled.", 'wpsearch'); ?></h3>
                    <p class="description"><?php printf(__('Please visit the <a href="%s" target="_blank">WP Search account page</a> to reactivate your subscription.', 'wpsearch'), 'http://wpsear.ch/account/'); ?></p>
                </div>
            <?php } elseif ($error == 'suspended') { ?>
                <div class="alert alert-critical">
                    <h3 class=""><?php esc_html_e("Your subscription is suspended.", 'wpsearch'); ?></h3>
                    <p class="description"><?php printf(__('Please contact <a href="%s" target="_blank">WP Search support</a> for assistance.', 'wpsearch'), 'http://wpsear.ch/contact/'); ?></p>
                </div>
            <?php } elseif ($error == 'limit-reached') {
                ?>
                <div class="alert alert-critical">
                    <h3 class=""><?php esc_html_e("Your WP Search account limits reached,you have more documents or do more search queries than your subscription allows.", 'wpsearch'); ?></h3>
                    <p class="description"><?php printf(__('If you would like to index more documents and do more search queries, you will need to <a href="%s" target="_blank">upgrade subscription</a>. If you have any questions, please <a href="%s" target="_blank">get in touch with our support team</a>', 'wpsearch'), 'http://wpsear.ch/account/upgrade/', 'http://wpsear.ch/contact/'); ?></p>

                </div>
            <?php } else {
                ?>
                <div class="alert alert-critical">
                    <h3 class=""><?php echo $error ?></h3>
                    <p class="description"><?php printf(__('Please contact <a href="%s" target="_blank">WP Search support</a> for assistance.', 'wpsearch'), 'http://wpsear.ch/contact/'); ?></p>

                </div> <?php
            }
        }
    }

    public function filterParseQuery($wp_query) {
        if (function_exists('is_main_query') && !$wp_query->is_main_query()) {
            return;
        }
        if (!is_search() || is_admin()) {
            return;
        }
        $wp_query->set('author', null);
    }

    public function filterPostLimits($limit) {

        if (is_search() && $this->searchSuccessful) {
            $limit = 'LIMIT 0, ' . count($this->postsAssoc);
        }
        return $limit;
    }

    public function filterClearSearchString($search) {
        if (is_search() && !is_admin() && $this->searchSuccessful) {

            $search = '';
        }
        return $search;
    }

    public function filterThePost($post) {

        if (!$this->searchSuccessful) {
            return $post;
        }
        if (isset($this->postsAssoc[$post->ID]) && isset($this->postsAssoc[$post->ID]['highlight'])) {
            $post->post_excerpt = $this->postsAssoc[$post->ID]['highlight'];
        }

        return $post;
    }

    public function filterThePosts($posts) {
        if (!is_search()) {
            return $posts;
        }
        if (!$this->searchSuccessful) {
            return $posts;
        }

        global $wp_query;
        $results = $this->results;
        $searchParams = $this->searchParams;
        $wp_query->max_num_pages = ceil($results['total'] / $searchParams['page_size']);
        $wp_query->found_posts = $results['total'];


        foreach ($posts as $key => $post) {
            if (isset($this->postsAssoc[$post->ID]) && isset($this->postsAssoc[$post->ID]['highlight'])) {
                $posts[$key]->post_excerpt = $this->postsAssoc[$post->ID]['highlight'];
            }
        }

        return $posts;
    }

    public function actionPreGetPosts($wp_query) {
        if (function_exists('is_main_query') && !$wp_query->is_main_query()) {
            return;
        }
        if (!is_search() || is_admin()) {
            return;
        }
        $params = array();
        $params['query'] = get_search_query(); //;
        $params['page'] = ( get_query_var('paged') ) ? get_query_var('paged') : 1;
        $params['queryname'] = "s";
        $params['pageName'] = "page";

        $params = $this->normaliseSearchParams($params);
        $results = $this->getResults($params);
        if (!$this->searchSuccessful) {
            return;
        }
        $post_ids = array();
        $postsAssoc = array();
        if ($results['results']) {
            foreach ($results['results'] as $result) {
                $post_ids[] = $result['id'];
                $postsAssoc[$result['id']] = $result;
            }
        }
        $this->postsAssoc = $postsAssoc;
        set_query_var('post__in', $post_ids);
    }

    public function getResults($params = null) {
        if ($this->searchSuccessful) {
            return $this->results;
        }

        if (!$params) {
            $params = $this->detectSearchParams();
        }
        $this->searchSuccessful = false;
        $params = $this->normaliseSearchParams($params);
        $this->searchParams = $params;
        $results = $this->getResultsFromApi($params);
        if (is_wp_error($results)) {
            $this->results = null;
            $this->searchSuccessful = false;
            return;
        }
        $this->searchSuccessful = true;
        $this->results = $results;
        return $this->results;


        return $this->results;
    }

    public function getSearchParams() {
        if (!$this->searchParams) {
            $this->searchParams = $this->detectSearchParams();
        }
        return $this->searchParams;
    }

    public function detectSearchParams() {
        $params = array();
        if (WPSearchUtils::isSearchPage()) {
            if (is_search()) {
                return $params;
            } else {
                global $post;
                preg_match_all('/' . get_shortcode_regex() . '/s', $post->post_content, $matches, PREG_SET_ORDER);

                if (empty($matches)) {
                    return $params;
                }
                foreach ($matches as $shortcode) {
                    if ("wpsearch" === $shortcode[2]) {
                        if ($shortcode[3]) {
                            $params = shortcode_parse_atts($shortcode[3]);
                        }
                        if ($shortcode[5]) {
                            $params['post_template'] = $shortcode[5];
                        }
                        break;
                    }
                }
                $params = apply_filters('wpsearch_post_detect_search_params', $params);
                return $params;
            }
        }
        return false;
    }

    public function handleAdminRequests() {

        $action = $_GET['wpsearchAction'];
        if ($action == "indexAll") {
            $wordpressIndexer = $this->getWPSearchIndexer();
            $lastId = (int) $_GET['lastId'];
            if (!$lastId) {
                $wordpressIndexer->indexStructure();
            }
            $response = $wordpressIndexer->indexAll($lastId);
            echo json_encode($response);
            exit;
        }
        if ($action == "deleteAll") {
            $apiClient = $this->getWPSearchApiClient();
            $return = $apiClient->deleteAll();
            $response = array();
            if (is_wp_error($return)) {
                $response["error"] = $return->get_error_message();
            } else {
                $response['status'] = 'ok';
            }
            echo json_encode($response);
            exit;
        }
    }

    public function deleteAll() {
        $apiClient = $this->getWPSearchApiClient();
        $return = $apiClient->deleteAll();
        if (!$return) {
            return false;
        } else {
            return true;
        }
    }

    public function indexAll() {
        $wordpressIndexer = $this->getWPSearchIndexer();
        $wordpressIndexer->indexStructure();
        return $wordpressIndexer->indexAll(0, true);
    }

    public static function templateRedirect() {

        if (!is_search()) {
            return;
        }
        if (file_exists(TEMPLATEPATH . '/wpsearch_search.php')) {
            include_once(TEMPLATEPATH . '/wpsearch_search.php');
        } elseif (file_exists(WPSearchUtils::pluginDir() . '/resource/template/wpsearch_search.php')) {
            include_once(WPSearchUtils::pluginDir() . '/resource/template/wpsearch_search.php');
        } else {
            // no template files found, just continue on like normal
            // this should get to the normal WordPress search results
            return;
        }

        exit;
    }

    public static function enqueueScripts() {
        $pluginUrl = WPSearchUtils::pluginUrl();
        wp_enqueue_script('wpsearch', $pluginUrl . 'resource/js/wpsearch.js', array('jquery'));
        wp_enqueue_style('wpsearch', $pluginUrl . 'resource/css/wpsearch.css');
    }

    public function doShortcode($params, $postTemplate = null) {
        $params['post_template'] = $postTemplate;
        $params['query'] = $_REQUEST['query'];
        $params['page'] = (int) $_GET['wpage'];
        $params['pageName'] = "wpage";
        $this->doSearch($params);
    }

    public function doSearch($params) {
        $params = $this->normaliseSearchParams($params);
        $results = $this->getResults($params);
        $this->render($results, $params);
    }

    public function getResultsFromApi($params) {
        $wordpressSearch = $this->getWPSearchSearch();
        $params = $this->normaliseSearchParams($params);
        return $wordpressSearch->search($params);
    }

    public function normaliseSearchParams($params) {
        if ($params['normalized']) {
            return $params;
        }

        $availableFacets = WPSearchUtils::getAvailableFacets();
        $availablePostTypes = WPSearchUtils::getAvailablePostTypesNames();
        $options = WPSearchOptions::getOptions();
        $pageSize = get_option('posts_per_page');
        if ((int) $options['page_size']) {
            $pageSize = $options['page_size'];
        }
        $showfacetsCount = 10;
        if ($options['show_facets_count']) {
            $showfacetsCount = $options['show_facets_count'];
        }

        $postTemplate = '';
        if ($options['post_template']) {
            $postTemplate = $options['post_template'];
        }
        $defaults = array(
            'page_size' => (int) $pageSize,
            'show_thumbnail' => false,
            'show_facets_count' => (int) $showfacetsCount,
            'post_template' => (int) $postTemplate,
        );


        $params = array_merge($defaults, $params);


        $params['normalized'] = true;

        $postTemplate = $params['post_template'];
        if (!$postTemplate) {
            $postTemplate = '<article id="post-$ID$" class="$POST_CLASS$" >
	$THUMBNAIL$
	<header class="entry-header">
		<div class="entry-meta">
			<span class="cat-links">$CATEGORIES_LINKS$</span>
		</div>
		<h1 class="entry-title">
		<a href="$PERMALINK$" rel="bookmark">
		$TITLE$
		</a>
		</h1>
		<div class="entry-meta">
			<span class="entry-date">
			<a href="$PERMALINK$" rel="bookmark">
			<time class="entry-date" >$DATE$</time></a>
			</span> <span class="byline"><span class="author vcard"><a href="$AUTHOR_URL$" rel="author">$AUTHOR$</a>
			</span></span>		</div>
	</header>

	<div class="entry-summary">
		$EXCERPT$
	</div>
	<footer class="entry-meta"><span class="tag-links">$TAGS_LINKS$</span></footer>
</article>';
        }
        $postTemplate = apply_filters('wpsearch_post_template', $postTemplate);

        $params['post_template'] = $postTemplate;


        if (is_search() && !$params['queryname']) {
            $params['queryname'] = "s";
        } elseif (!$params['queryname']) {
            $params['queryname'] = "query";
        }
        if (is_search() && !$params['pageName']) {
            $params['pageName'] = "page";
        } elseif (!$params['pageName']) {
            $params['pageName'] = "wpage";
        }
        if ($options['search_sort'] && $options['search_sort'] != "relevance") {
            $params['sort'] = $options['search_sort'];
        }

        if (!$params['post_types']) {
            $params['post_types'] = WPSearchUtils::getDefaultPostTypes();
        } else {
            $postTypes = array();
            if (is_array($params['post_types'])) {
                $postTypes = array_values($params['post_types']);
            } else {
                $postTypes = explode(",", $params['post_types']);
            }
            foreach ($postTypes as $key => $postType) {
                if (!in_array($postType, $availablePostTypes)) {
                    // unset($postTypes[$key]);
                }
            }
            sort($postTypes);
            $params['post_types'] = $postTypes;
        }

        if (!$params['facets']) {
            $params['facets'] = WPSearchUtils::getDefaultFacets();
        } else {
            if (is_array($params['facets'])) {
                $facets = array();
                $facets2 = $params['facets'];
                foreach ($facets2 as $key => $values) {
                    $facetTitle = "";
                    if (is_array($values)) {//array("tax1"=>array("title"=>"","facet"=>"tax1"
                        $facetField = $values['facet'];
                        $facetTitle = $values['title'];
                    } elseif (is_numeric($key)) {//array("tax1","tax2")
                        $facetField = $values;
                    } else {//array("tax1"=>"Tax1,"tax2"=>"tax2)
                        $facetField = $key;
                        $facetTitle = $values;
                    }
                    if (!$availableFacets[$facetField]) {
                        continue;
                    }
                    if (!$facetTitle) {
                        $facetTitle = $availableFacets[$facetField]['title'];
                    }
                    $facets[$facetField] = array("title" => $facetTitle,
                        "facet" => $availableFacets[$facetField]['facet'],
                        "type" => $availableFacets[$facetField]['type']);
                }
                $params['facets'] = $facets;
            } else {//from shortcode,facets="category|Cat,tax1|Tax1"
                $facets = array();
                $facets2 = explode(",", $params['facets']);

                foreach ($facets2 as $val) {
                    $values = explode("|", $val);
                    $facetField = $values[0];
                    if (!$availableFacets[$facetField]) {
                        continue;
                    }
                    if ($values[1]) {
                        $facetTitle = $values[1];
                    } else {
                        $facetTitle = $availableFacets[$facetField]['title'];
                    }
                    $facets[$facetField] = array("title" => $facetTitle,
                        "facet" => $availableFacets[$facetField]['facet'],
                        "type" => $availableFacets[$facetField]['type']);
                }
                $params['facets'] = $facets;
            }
        }



        $fq = array();
        global $polylang;
        if ($options['wpml_current_lang']) {
            if (function_exists('wpml_get_language_information')) {
                global $sitepress;
                $fq['language'] = array("values" => (array) strtolower($sitepress->get_locale(icl_get_current_language()))); //ICL_LANGUAGE_CODE against icl_get_current_language()
            }
            if (function_exists('pll_default_language')) {
                $lang = pll_current_language('locale');
                if ($lang) {
                    $fq['language'] = array("values" => (array) strtolower($lang));
                }
            }
        }

        foreach ($params['facets'] as $val) {
            $facetField = $val['facet'];
            $values = $_GET[$facetField];
            if (!$values) {
                continue;
            }
            $values = (array) $values;
            foreach ($values as $key => $value) {
                $value = trim($value);
                if (!$value) {
                    unset($values[$key]);
                }
            }
            if ($values) {
                if ($val['type'] == "range") {
                    $fq[$facetField] = array("values" => $values, "type" => "range");
                } elseif ($val['type'] == "daterange") {
                    $range = $values; //explode("-", $values);
                    $range = array_map("trim", $range);

                    $range2 = array();
                    if ($range['from'] && strtotime($range['from'])) {
                        $range2['from'] = date("Y-m-d", strtotime($range['from']));
                    }
                    if ($range['to'] && strtotime($range['to'])) {
                        $range2['to'] = date("Y-m-d", strtotime($range['to']));
                    }
                    if ($range2) {
                        $fq[$facetField] = array("values" => array($range2), "type" => "daterange");
                    }
                } else {
                    $fq[$facetField] = array("values" => (array) $values);
                }
            }
        }
        $params['where'] = $fq;

        $show_thumbnail = false;
        if (isset($params['show_thumbnail'])) {
            $show_thumbnail = (bool) $params['show_thumbnail'];
        }


        if (isset($params['page'])) {
            $page = intval($params['page']);
        } else {
            if (get_query_var('paged')) {
                $page = get_query_var('paged');
            }
            if (get_query_var('page')) {
                $page = get_query_var('page');
            }
        }

        $page = intval($page);
        if ($page < 1) {
            $page = 1;
        }
        $params['page'] = $page;

        $params['page_size'] = (int) $pageSize;
        if ($params['query']) {
            $params['query'] = trim($params['query']);
        }
        $params = apply_filters('wpsearch_post_normalise_search_params', $params);

        return $params;
    }

    public function getFacetsRender($results = null, $params = null) {
        if ($this->facetsRender) {
            return $this->facetsRender;
        }
        if (!$results) {
            $results = $this->getResults($params);
            $params = $this->searchParams;
        }

        $baseUrl = WPSearchUtils::getBaseUrl();
        $baseUrl = preg_replace('~(\?|&)' . $params['pageName'] . '=[^&]*~', '$1', $baseUrl);

        $resetUrl = WPSearchUtils::getResetUrl();

        $response = array();
        $response['activefacets'] = '';
        if ($results['existactivefacet']) {
            ob_start();
            do_action('wpsearch_render_activefacets', $results, $params);
            $template = ob_get_contents();
            ob_end_clean();

            ob_start();
            if ($template) {
                echo $template;
            } else {
                ?>

                <h3 class="activefacettitle facetlabel"><?php echo __('Active facets', 'wpsearch') ?></h3>
                <ul class="facet_values activefacets">
                    <?php
                    foreach ($results['facets'] as $facetName => $facet) {
                        if ($facet["data"]) {
                            foreach ($facet["data"] as $data) {
                                if ($data["active"]) {
                                    $url = str_replace($facetName . "[]=" . $data["val"], "", $baseUrl);
                                    $url = WPSearchUtils::fixUrl($url);
                                    ?>
                                    <li class="remove">
                                        <a href="<?php echo $url; ?>"><?php echo $data["title"] ?></a></li>
                                    <?php
                                }
                            }
                        }
                    }
                    ?>
                    <li class="reset"><a href="<?php echo $resetUrl ?>"><?php echo __('Reset all', 'wpsearch') ?></a></li>
                </ul>
                <?php
            }

            $response['activefacets'] = ob_get_contents();

            ob_end_clean();
        }

        ob_start();
        do_action('wpsearch_render_searchinput', $results, $params);
        $template = ob_get_contents();
        ob_end_clean();

        ob_start();
        if ($template) {
            echo $template;
        } else {
            ?>

            <form method="get" action="<?php echo $baseUrl ?>">
                <?php
                global $wp_rewrite;
                if (!$wp_rewrite->using_permalinks()) {
                    global $wp;
                    $queryParams = array();
                    parse_str($wp->query_string, $queryParams);
                    if ($queryParams) {
                        unset($queryParams[$params['queryname']]);
                        foreach ($queryParams as $key => $value) {
                            if (is_array($value)) {
                                foreach ($value as $k => $v) {
                                    ?>
                                    <input name="<?php echo $key ?>[]" type="hidden" value="<?php echo $v ?>" />
                                    <?php
                                }
                            } else {
                                ?>
                                <input name="<?php echo $key ?>" type="hidden" value="<?php echo $value ?>" />
                                <?php
                            }
                        }
                    }
                }
                ?>
                <input class="wpsearch_search_input" id="text_search" name="<?php echo $params['queryname'] ?>" type="text" value="<?php if ($params['query']) echo htmlentities(trim($params['query']), ENT_QUOTES, "UTF-8"); ?>"/>
                <input class="wpsearch_search_btn" type="submit" value="<?php echo __('Search', 'wpsearch') ?>" />

            </form>
            <?php
        }
        $response['searchinput'] = ob_get_contents();

        ob_end_clean();
        $response['facets'] = '';



        $template = "";
        if ($results['facets']) {


            foreach ($results['facets'] as $facetName => $facet) {


                if ($facet["type"] == "daterange") {
                    ob_start();
                    do_action('wpsearch_render_facets_daterange', $results, $params);
                    $templateDaterange = ob_get_clean();

                    ob_start();
                    if ($templateDaterange) {
                        echo $templateDaterange;
                    } else {
                        wp_enqueue_script('jquery-ui-datepicker');
                        wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');
                        ?>
                        <div class="wpsearch_daterange_facet" >
                            <h3 class="facetlabel">
                                <?php echo $facet["title"] ?>
                            </h3>
                            <div  class="facet_values_container" id="div_facets_<?php echo $facetName ?>">
                                <ul  id="facets_<?php echo $facetName ?>">

                                    <form method="get" action="<?php echo $baseUrl ?>">
                                        <?php
                                        $urlParts = parse_url($baseUrl);
                                        $urlParts = $urlParts['query'];
                                        if ($urlParts) {
                                            $queryParams = array();
                                            parse_str($urlParts, $queryParams);
                                            if ($queryParams) {
                                                unset($queryParams[$facetName]);
                                                foreach ($queryParams as $key => $value) {
                                                    if (is_array($value)) {
                                                        foreach ($value as $k => $v) {
                                                            ?>
                                                            <input name="<?php echo $key ?>[]" type="hidden" value="<?php echo $v ?>" />
                                                            <?php
                                                        }
                                                    } else {
                                                        ?>
                                                        <input name="<?php echo $key ?>" type="hidden" value="<?php echo $value ?>" />
                                                        <?php
                                                    }
                                                }
                                            }
                                        }
                                        ?>
                                        <h3><?php echo __('Start date', 'wpsearch') ?></h3>
                                        <input class="wpsearch_search_input wpsearch_daterange_start datepicker" id="date_range_start" name="<?php echo $facetName ?>[from]" type="text" value="<?php if ($_GET[$facetName]['from']) echo htmlentities(trim($_GET[$facetName]['from']), ENT_QUOTES, "UTF-8"); ?>"/>
                                        <br/>
                                        <h3><?php echo __('End date', 'wpsearch') ?></h3>
                                        <input class="wpsearch_search_input wpsearch_daterange_end datepicker" id="date_range_end" name="<?php echo $facetName ?>[to]" type="text" value="<?php if ($_GET[$facetName]['to']) echo htmlentities(trim($_GET[$facetName]['to']), ENT_QUOTES, "UTF-8"); ?>"/>
                                        <br/><input class="wpsearch_search_btn" type="submit"  value="<?php echo __('Search', 'wpsearch') ?>" />

                                    </form>
                                    <script>

                                        jQuery(document).ready(function($) {
                                            jQuery(".datepicker").datepicker({
                                                dateFormat: "yy-mm-dd",
                                                altFormat: "yy-mm-dd",
                                                changeMonth: true,
                                                changeYear: true,
                                                yearRange: "1902:2037",
                                            });
                                        });
                                    </script>
                                </ul>
                            </div>
                        </div> <?php
                    }
                    $template .= ob_get_clean();
                } elseif ($facet["data"]) {
                    ob_start();
                    do_action('wpsearch_render_facets_taxonomy', $results, $params, $facetName, $facet);
                    $templateTaxonomy = ob_get_clean();

                    ob_start();
                    if ($templateTaxonomy) {
                        echo $templateTaxonomy;
                    } else {
                        ?>
                        <div class="wpsearch_taxonomy_facet" >

                            <h3 class="facetlabel">
                                <?php echo $facet["title"] ?>
                            </h3>
                            <div class="facet_values_container" id="div_facets_<?php echo $facetName ?>">
                                <ul class="facet_values" id="facets_<?php echo $facetName ?>">
                                    <?php
                                    $i = 0;
                                    $hidenexist = false;
                                    foreach ($facet["data"] as $data) {
                                        $i++;
                                        $url = WPSearchUtils::fixUrl($baseUrl . "&" . $facetName . "[]=" . $data["val"]);
                                        if ($data["active"]) {
                                            $url = str_replace($facetName . "[]=" . $data["val"], "", $baseUrl);
                                            $url = WPSearchUtils::fixUrl($url);
                                        }
                                        ?>
                                        <li<?php
                                        if ($data["active"]) {
                                            echo ' class="active" ';
                                        } elseif ($i > $params['show_facets_count']) {
                                            $hidenexist = true;
                                            echo " style='display:none' class='hidden_facets' ";
                                        }
                                        ?>>
                                            <em><?php echo $data["count"] ?></em>

                                            <a   href="<?php echo $url; ?>" class="facet_level<?php echo (int) $data["level"] ?>"  >
                                                <?php echo $data["title"] ?></a> </li>
                                        <?php
                                    }
                                    ?>
                                </ul>

                            </div>
                            <?php if ($hidenexist) {
                                ?>

                                <div class="clrfix showalldiv" id="show_all_facets_<?php echo $facetName ?>">
                                    <a data-alias="<?php echo $facetName ?>" data-status="hide" class="show_more show_all_facets" href="#"><span><?php echo __('Show more', 'wpsearch') ?></span></a>
                                </div>
                                <?php
                            }
                            ?>
                        </div><?php
                    }
                    $template .= ob_get_clean();
                }
            }
        }

        $response['facets'] = $template;
        $response = apply_filters('wpsearch_post_facets_render', $response);
        $this->facetsRender = $response;
        return $this->facetsRender;
    }

    public function getResultsRender($results = null, $params = null) {
        if ($this->resultsRender) {
            return $this->resultsRender;
        }
        if (!$results) {
            $results = $this->getResults($params);
            $params = $this->searchParams;
        }
        $show_thumbnail = false;
        if (isset($params['show_thumbnail'])) {
            $show_thumbnail = (bool) $params['show_thumbnail'];
        }

        $postTemplate = $params['post_template'];

        $queryname = $params['queryname'];

        $baseUrl = WPSearchUtils::getBaseUrl();
        $response = array();
        if ($results['total']) {
            $current = (isset($params['page'])) ? $params['page'] : 1;
            $start = ($current - 1) * $params['page_size'] + 1;
            $end = $current * $params['page_size'];
            if ($end > $results['total']) {
                $end = $results['total'];
            }

            $url = preg_replace('~(\?|&)' . $params['pageName'] . '=[^&]*~', '$1', $baseUrl);
            $query_args = array();
            $url_parts = explode('?', $url);

            if (isset($url_parts[1])) {
                wp_parse_str($url_parts[1], $query_args);
            }
            $response['pagination'] = paginate_links(array(
                'base' => WPSearchUtils::fixUrl($url . '&' . $params['pageName'] . '=%#%'),
                'format' => '?' . $params['pageName'] . '=%#%',
                'current' => max(1, $current),
                'total' => ceil($results['total'] / $params['page_size']),
                'add_args' => WPSearchUtils::array_map('urlencode', $query_args)
            ));
            $response['start'] = $start;
            $response['end'] = $end;
            $response['total'] = $results['total'];
        }
        $response['content'] = '';
        if ($results['results']) {
            foreach ($results['results'] as $result) {
                $content = "";

                $post = get_post($result['id']);

                if (isset($result['highlight'])) {
                    $post->post_excerpt = $result['highlight'];
                }
                setup_postdata($post);
                $templateParams = array(
                    '$ID$' => $result['id'],
                    '$POST_CLASS$' => join(' ', get_post_class($class, $result['id'])),
                    '$PERMALINK$' => get_permalink($result['id']),
                    '$POST_TYPE$' => $post->post_type,
                    '$TITLE$' => $result['post_title'],
                    '$EXCERPT$' => rtrim(str_replace(array("<p>", "</p>"), "", apply_filters('the_excerpt', apply_filters('get_the_excerpt', $post->post_excerpt))), "[...]"),
                    '$DATE$' => mysql2date(get_option('date_format'), $post->post_date),
                );
                if (has_post_thumbnail($result['id'])) {
                    $templateParams['$THUMBNAIL$'] = '<div class="post-thumbnail">' . get_the_post_thumbnail($result['id']) . '</div>';
                }
                $templateParams['$CATEGORIES_LINKS$'] = get_the_category_list(', ', '', $result['id']);
                $templateParams['$TAGS_LINKS$'] = get_the_tag_list();
                $templateParams['$AUTHOR_URL$'] = get_author_posts_url($result['author']);
                $templateParams['$AUTHOR$'] = get_the_author_meta('display_name', $result['author']);
                $templateParams = apply_filters('wpsearch_template_params', $templateParams, $result);

                $content = str_replace(array_keys($templateParams), $templateParams, $postTemplate);
                $content = apply_filters('wpsearch_result_render', $content, $templateParams, $result);

                $response['content'] = $response['content'] . $content;
            }
        }



        $response = apply_filters('wpsearch_post_results_render', $response);
        $this->resultsRender = $response;
        return $this->resultsRender;
    }

    public function render($results = null, $params = null) {
        if (!$results) {
            $results = $this->getResults($params);
            $params = $this->searchParams;
        }

        $response = $this->getResultsRender($results, $params) + $this->getFacetsRender($results, $params);
        ob_start();
        do_action('wpsearch_render', $response, $results, $params);
        $template = ob_get_contents();
        ob_end_clean();

        if ($template) {
            echo $template;
        } else {
            $mainClass = "fullwidth";

            $options = WPSearchOptions::getOptions();

            if ($options['search_page_layout'] == "right_sidebar") {
                $mainClass = "with_right_sidebar";
                $sidebarClass = "right_sidebar";
            }
            if ($options['search_page_layout'] == "left_sidebar") {
                $mainClass = "with_left_sidebar";
                $sidebarClass = "left_sidebar";
            }
            ?>
            <div class="wpsearch_container"  >
                <div class="wpsearch_main <?php echo $mainClass ?>" >
                    <div class="wpsearch_pagination  pagination">
                        <?php
                        if ($response['total']) {
                            ?>
                            <span class="counts_text" >
                                <?php printf(__('%1$s - %2$s of %3$s results', 'wpsearch'), $response['start'], $response['end'], $response['total']); ?>
                            </span>
                            <?php
                            echo $response['pagination'];
                        }
                        ?>
                    </div> 
                    <div class="wpsearch_results">
                        <?php
                        if ($response['total'] === "0") {
                            ?><div class='wpsearch_noresult'>
                                <h2><?php echo __('Sorry, no results were found.', 'wpsearch') ?></h2>
                            </div>
                            <?php
                        } else {
                            echo $response['content'];
                        }
                        ?>
                        <div class="wpsearch_pagination  pagination">
                            <?php
                            if ($response['total']) {
                                ?>
                                <span class="counts_text" >
                                    <?php printf(__('%1$s - %2$s of %3$s results', 'wpsearch'), $response['start'], $response['end'], $response['total']); ?>
                                </span>
                                <?php
                                echo $response['pagination'];
                            }
                            ?>
                        </div>
                    </div>	
                </div>
                <?php
                if (in_array($options['search_page_layout'], array("right_sidebar", "left_sidebar"))) {
                    ?>
                    <div class="wpsearch_sidebar <?php echo $sidebarClass ?>"  >
                        <?php
                        if ($response['facets'] || $response['searchinput']) {
                            echo $response['activefacets'];
                            ?>
                            <div class="wpsearch_searchinput_container">
                                <?php echo $response['searchinput']; ?>
                            </div>
                            <?php
                            echo $response['facets'];
                        }
                        ?>
                    </div>
                <?php }
                ?>
            </div>
            <?php
        }
    }

    public function getWPSearchApiClient() {
        return $this->wpsearchApiClient;
    }

    public function getWPSearchIndexer() {
        return $this->wpsearchIndexer;
    }

    public function getWPSearchSearch() {
        return $this->wpsearchSearch;
    }

}
?>