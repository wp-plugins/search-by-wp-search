<?php
if (!class_exists('Redux_Framework_WPSearch_config')) {

    class Redux_Framework_WPSearch_config {

        public $args = array();
        public $sections = array();
        public $ReduxFramework;

        public function __construct() {

            if (!class_exists('ReduxFramework')) {
                return;
            }

            add_action('init', array($this, 'initSettings'), 10);
            add_action('admin_head', array($this, 'adminHead'));
        }

        public static function adminHead() {
            $pluginUrl = WPSearchUtils::pluginUrl();

            // include css
            ?>
            <script type="text/javascript">


                var wpsearch_ajax_url = '<?php echo admin_url('admin-ajax.php?action=wpsearch_admin_requests'); ?>';

                //var $j = jQuery.noConflict();



                Array.prototype.inArray = function(p_val) {
                    var l = this.length;
                    for (var i = 0; i < l; i++) {
                        if (this[i] == p_val) {
                            return true;
                        }
                    }
                    return false;
                }

                function doWpsearchIndex(lastId) {
                    if (lastId == 0) {
                        setWpSearchProgress({percent: 0})
                        jQuery('#wpsearch_index_status').html('&nbsp;<img src="<?php print $pluginUrl; ?>resource/images/ajax-circle.gif"> 0%');
                    }
                    jQuery.get(wpsearch_ajax_url, {wpsearchAction: 'indexAll', lastId: lastId}, doWpsearchIndexHandleResults, "json");
                }
                function doWpsearchIndexHandleResults(data) {

                    if (data.error) {
                        showWpSearchError(data.error);
                        return;
                    }
                    setWpSearchProgress(data)
                    if (!data.end) {
                        doWpsearchIndex(data.lastId);
                    }
                }
                function setWpSearchProgress(data) {

                    var progress_width = Math.round(data.percent / 100 * 245);
                    if (progress_width < 10) {
                        progress_width = 10;
                    }
                    if (data.percent == 0) {
                        jQuery('#progress_bar').fadeIn();
                    }
                    //jQuery('#num_indexed_documents').html(total_posts_written);
                    jQuery('#progress_bar').find('div.bar').show().width(progress_width);
                    if (data.end) {
                        jQuery('#wpsearch_btn_index').val('<?php echo __('Indexing Complete!', 'wpsearch') ?>');
                        jQuery('#progress_bar').fadeOut();
                        jQuery('#wpsearch_btn_index').unbind();
                    } else {
                        jQuery('#wpsearch_btn_index').val('<?php echo __('Indexing progress... ', 'wpsearch') ?>' + Math.round(data.percent) + '%');
                    }
                }
                function showWpSearchError(message) {
                    jQuery('#wpsearch_synchronizing').fadeOut();
                    jQuery('#wpsearch_synchronize_error').fadeIn();
                    if (message.length > 0) {
                        jQuery('#wpsearch_error_text').append(message).show();
                    }
                }

                jQuery(document).ready(function($) {



                    $('[name=wpsearch_btn_index]').click(function(e) {
                        e.preventDefault();
                        doWpsearchIndex(0);
                    });

                    $('[name=wpsearch_btn_deleteall]').click(function(e) {
                        e.preventDefault();
                        $('#wpsearch_deleteall_status').html('&nbsp;<img src="<?php print $pluginUrl; ?>resource/images/ajax-circle.gif">');
                        $.get(wpsearch_ajax_url, {wpsearchAction: 'deleteAll'},
                        function(data) {
                            var resp = JSON.parse(data);

                            if (resp.error) {
                                showWpSearchError(resp.error)
                            }
                            else {
                                $('#wpsearch_deleteall_status').html('&nbsp;<img src="<?php print $pluginUrl; ?>resource/images/success.png">');
                            }
                        });

                        return false;
                    });


                });
            </script>






            <?php
        }

        public function initSettings() {

            // Set the default arguments
            $this->setArguments();

            // Set a few help tabs so you can see how it's done
            $this->setHelpTabs();

            // Create the sections and fields
            $this->setSections();

            if (!isset($this->args['opt_name'])) { // No errors please
                return;
            }

            // If Redux is running as a plugin, this will remove the demo notice and links
            add_action('redux/loaded', array($this, 'remove_demo'));

            $this->ReduxFramework = new ReduxFramework($this->sections, $this->args);
        }

        // Remove the demo link and the notice of integrated demo from the redux-framework plugin
        function remove_demo() {

            // Used to hide the demo mode link from the plugin page. Only used when Redux is a plugin.
            if (class_exists('ReduxFrameworkPlugin')) {
                remove_filter('plugin_row_meta', array(ReduxFrameworkPlugin::instance(), 'plugin_metalinks'), null, 2);

                // Used to hide the activation notice informing users of the demo panel. Only used when Redux is a plugin.
                remove_action('admin_notices', array(ReduxFrameworkPlugin::instance(), 'admin_notices'));
            }
        }

        public function setSections() {



            ob_start();
            ?>
            <div id="current-theme" >


                <h4>WP Search</h4>

            </div>

            <?php
            $item_info = ob_get_contents();

            ob_end_clean();

            $sampleHTML = '';

            $postTypes = get_post_types(array(), 'objects');
            $indexPostTypes = array();
            $defaultPostTypes = array();
            foreach ($postTypes as $type => $postType) {
                if ('nav_menu_item' == $type || 'revision' == $type) {
                    continue;
                }
                $indexPostTypes[$type] = $postType->label;
                if ($postType->public) {
                    $defaultPostTypes[$type] = '1';
                }
            }
            $this->sections[] = array(
                'icon' => 'el-icon-cogs',
                'title' => __('General Settings', 'wpsearch'),
                'fields' => array(
                    array(
                        'id' => 'api_key',
                        'type' => 'text',
                        'title' => __('API key', 'wpsearch'),
                        'subtitle' => __('Get your API key at <a href="http://wpsear.ch" target="_blank">http://wpsear.ch</a>', 'wpsearch'),
                        'validate_callback' => 'wpsearch_validate_api_key_callback',
                        'msg' => __('Wrong api key', 'wpsearch'),
                        'default' => '',
                        'text_hint' => array(
                            'title' => __('Valid API key required!', 'wpsearch'),
                            'content' => __('Get your API key at http://wpsear.ch', 'wpsearch'),
                        )
                    ), array(
                        'id' => 'actions',
                        'type' => 'callback',
                        'title' => __('Actions', 'wpsearch'),
                        'callback' => 'wpsearch_actions_callback'
                    ),
                    /* array(
                      'id' => 'error_logging',
                      'type' => 'switch',
                      'title' => __('WP Search error logging', 'wpsearch'),
                      'default' => false,
                      ), */
                    array(
                        'id' => 'index_post_types',
                        'type' => 'checkbox',
                        'title' => __('Post types to index', 'wpsearch'),
                        'options' => $indexPostTypes,
                        'default' => $defaultPostTypes,
                    ),
                    array(
                        'id' => 'exclude_post_ids',
                        'type' => 'text',
                        'title' => __('Exclude these posts/pages from search', 'wpsearch'),
                        'subtitle' => __("Enter a comma-separated list of post/page/custom post IDs that are excluded from search results. This only works here, you can't use the input field option (WordPress doesn't pass custom parameters there). You can also use a checkbox on post/page edit pages to remove posts from index.", 'wpsearch'),
                        'validate_callback' => 'wpsearch_validate_exclude_post_ids_callback',
                        'default' => ''
                    ),
                    array(
                        'id' => 'post_template',
                        'type' => 'textarea',
                        'title' => __('Single Post template for search page', 'wpsearch'),
                        'subtitle' => __('You can use $ID$, $TITLE$, $EXCERPT$, $DATE$, $THUMBNAIL$, $POST_CLASS$, $PERMALINK$, $POST_TYPE$, $CATEGORIES_LINKS$, $TAGS_LINKS$, $AUTHOR_URL$, $AUTHOR$', 'wpsearch'),
                        'default' => '<article id="post-$ID$" class="$POST_CLASS$" >
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
</article>'
                    ),
                    array(
                        'id' => 'batch_size',
                        'type' => 'text',
                        'title' => __('Index batch size', 'wpsearch'),
                        'validate' => 'numeric',
                        'default' => '100',
                    ),
            ));
            $this->sections[0]['fields'][] = array(
                'id' => 'use_https',
                'type' => 'checkbox',
                'title' => __('Use https for API queries', 'wpsearch'),
                'default' => '0'
            );
            if (function_exists('icl_object_id')) {
                $this->sections[0]['fields'][] = array(
                    'id' => 'wpml_current_lang',
                    'type' => 'checkbox',
                    'title' => __('WPML/Polylang compatibility(Limit results to current language)', 'wpsearch'),
                    'subtitle' => __("If this option is checked,  will only return results in the current active language. Otherwise results will include posts in every language.", 'wpsearch'),
                    'default' => '1'
                );
            }


            $allFacetsTitle = WPSearchUtils::getAvailableFacetsTitles();
            $this->sections[1] = array(
                'icon' => 'el-icon-th-list',
                'title' => __('Search Settings', 'wpsearch'),
                'fields' => array(
                    array(
                        'id' => 'page_size',
                        'type' => 'text',
                        'title' => __('Posts per page', 'wpsearch'),
                        'validate' => 'numeric',
                        'default' => get_option('posts_per_page'),
                    ), array(
                        'id' => 'search_sort',
                        'type' => 'select',
                        'title' => __('Search page results order', 'wpsearch'),
                        'desc' => '',
                        'options' => array(
                            'relevance' => __('Relevance', 'wpsearch'),
                            'post_date' => __('Date', 'wpsearch'),
                        ), 'default' => 'relevance'
                    ),
                    array(
                        'id' => 'search_page_behavior',
                        'type' => 'select',
                        'title' => __('Search page behavior', 'wpsearch'),
                        'desc' => __('How you want to be search page.Allow WP Search to replace content or use existing search page and adjust posts by pre_get_posts', 'wpsearch'),
                        'options' => array(
                            'wpsearch' => __('Use WP Search search page', 'wpsearch'),
                            'use_existing' => __('Dont replace search page, use pre_get_posts', 'wpsearch'),
                        ), 'default' => 'wpsearch'
                    ),
                    array(
                        'id' => 'search_page_layout',
                        'type' => 'select',
                        'title' => __('Search page layout', 'wpsearch'),
                        'desc' => __('Organize how you want to be search pages.You can add search facets widget in WordPress widgets section', 'wpsearch'),
                        'options' => array(
                            'right_sidebar' => __('Facets on right', 'wpsearch'),
                            'left_sidebar' => __('Facets on left', 'wpsearch'),
                            'no_sidebar' => __('No Facets,I will configure widget', 'wpsearch'),
                        ), 'default' => 'right_sidebar'
                    ),
                    array(
                        'id' => 'show_facets_count',
                        'type' => 'text',
                        'title' => __('How many facet values to show when collapsed', 'wpsearch'),
                        'validate' => 'numeric',
                        'default' => 10,
                    ),
                    array(
                        'id' => 'search_page_facets',
                        'type' => 'sorter',
                        'title' => __('Search facets', 'wpsearch'),
                        'desc' => __('Organize what facets you want to appear on the search page', 'wpsearch'),
                        'compiler' => 'true',
                        'options' => array(
                            'enabled' => $allFacetsTitle, 'disabled' => array(
                            ),
                        ),
                    ),
                )
            );
            $allFacets = WPSearchUtils::getAvailableFacets();
            $defaultOrderOptions = array("count" => __("Count of match", 'wpsearch'), "alphabetical" => __("Alphabetical (A-Z)", 'wpsearch'),
                "alphabeticalreverse" => __("Alphabetical (Z-A)", 'wpsearch'),);

            $this->sections[2] = array(
                'icon' => 'el-icon-lines',
                'title' => __('Facets Ordering', 'wpsearch'),
            );
            foreach ($allFacets as $facetName => $facet) {
                if (in_array($facet['type'], array('taxonomy', 'post_type', 'author', 'year'))) {
                    $orderOptions = $defaultOrderOptions;
                    if (isset($facet['hierarchical']) && $facet['hierarchical']) {
                        $orderOptions['hierarchical'] = __('Hierarchical', 'wpsearch');
                    }
                    $this->sections[2]['fields'][] = array(
                        'id' => 'facet_ordering_' . $facetName,
                        'type' => 'select',
                        'title' => __($facet['title'], 'wpsearch'),
                        'options' => $orderOptions,
                        'default' => 'count'
                    );
                }
            }
        }

        public function setHelpTabs() {
            
        }

        public function setArguments() {


            $this->args = array(
                'opt_name' => 'wpsearch_settings', // This is where your data is stored in the database and also becomes your global variable name.
                'display_logo' => WPSearchUtils::pluginUrl() . "resource/images/logo.png",
                'display_name' => "<span style='float: left;
margin-top: 26px;
margin-left: 30px;'>WP Search - Powerful search for WordPress</span>", // Name that appears at the top of your panel
                //'display_version' =>"<span style='float:left'>".WPSEARCH_VERSION."</span>", // Version that appears at the top of your panel
                'menu_type' => 'menu', //Specify if the admin menu should appear or not. Options: menu or submenu (Under appearance only)
                'allow_sub_menu' => true, // Show the sections below the admin menu item or not
                'menu_title' => __('WP Search', 'wpsearch'),
                'page_title' => __('WP Search', 'wpsearch'),
                // You will need to generate a Google API key to use this feature.
                // Please visit: https://developers.google.com/fonts/docs/developer_api#Auth
                'google_api_key' => '', // Must be defined to add google fonts to the typography module
                'async_typography' => false, // Use a asynchronous font on the front end or font string
                'admin_bar' => true, // Show the panel pages on the admin bar
                'global_variable' => '', // Set a different name for your global variable other than the opt_name
                'dev_mode' => false, // Show the time the page took to load, etc
                'customizer' => true, // Enable basic customizer support
                // OPTIONAL -> Give you extra features
                'page_priority' => null, // Order where the menu appears in the admin area. If there is any conflict, something will not show. Warning.
                'page_permissions' => 'manage_options', // Permissions needed to access the options panel.
                'menu_icon' => WPSearchUtils::pluginUrl() . 'resource/images/menu-icon.png', // Specify a custom URL to an icon
                'last_tab' => '', // Force your panel to always open to a specific tab (by id)
                'page_icon' => 'icon-themes', // Icon displayed in the admin panel next to your menu_title
                'page_slug' => 'wpsearch_options', // Page slug used to denote the panel
                'save_defaults' => true, // On load save the defaults to DB before user clicks save or not
                'default_show' => false, // If true, shows the default value next to each field that is not the default value.
                'default_mark' => '', // What to print by the field's title if the value shown is default. Suggested: *
                'show_import_export' => false, // Shows the Import/Export panel when not used as a field.
                // CAREFUL -> These options are for advanced use only
                'transient_time' => 60 * MINUTE_IN_SECONDS,
                'output' => true, // Global shut-off for dynamic CSS output by the framework. Will also disable google fonts output
                'output_tag' => true, // Allows dynamic CSS to be generated for customizer and google fonts, but stops the dynamic CSS from going to the head
                'footer_credit' => '', // Disable the footer credit of Redux. Please leave if you can help it.
                // FUTURE -> Not in use yet, but reserved or partially implemented. Use at your own risk.
                'database' => '', // possible: options, theme_mods, theme_mods_expanded, transient. Not fully functional, warning!
                'system_info' => false, // REMOVE
                // HINTS
                'hints' => array(
                    'icon' => 'icon-question-sign',
                    'icon_position' => 'right',
                    'icon_color' => 'lightgray',
                    'icon_size' => 'normal',
                    'tip_style' => array(
                        'color' => 'light',
                        'shadow' => true,
                        'rounded' => false,
                        'style' => '',
                    ),
                    'tip_position' => array(
                        'my' => 'top left',
                        'at' => 'bottom right',
                    ),
                    'tip_effect' => array(
                        'show' => array(
                            'effect' => 'slide',
                            'duration' => '500',
                            'event' => 'mouseover',
                        ),
                        'hide' => array(
                            'effect' => 'slide',
                            'duration' => '500',
                            'event' => 'click mouseleave',
                        ),
                    ),
                )
            );




            $this->args['intro_text'] = '';

            $this->args['footer_credit'] = '<span id="footer-thankyou">' . sprintf(__('%1$s version %2$s', 'wpsearch'), '<a href="http://wpsear.ch" target="_blank">' . __('WP Search', 'wpsearch') . '</a>', WPSEARCH_VERSION) . '</span>';
        }

    }

    global $reduxConfig;
    $reduxConfig = new Redux_Framework_WPSearch_config();
}

/**
  Custom function for the callback referenced above
 */
if (!function_exists('wpsearch_actions_callback')):

    function wpsearch_actions_callback($field, $value) {
        ?> 
        <style>

            .wpsearch_progress .bar {
                -webkit-border-radius: 7px;
                -moz-border-radius: 7px;
                border-radius: 7px;
                cursor: pointer;
                display: block;
                width: 245px;
                height: 10px;
                background-color: blue;
                background: -moz-linear-gradient(top, #00BBF8 0%, #0089F4);
                background: -webkit-gradient(linear, left top, left bottom, from(#00BBF8), to(#0089F4));
                border: 1px solid #3F6F99;
                -moz-box-shadow: inset 0px 1px 0px #00dbfc;
                -webkit-box-shadow: inset 0px 1px 0px #00dbfc;
            }

            .wpsearch_progress {
                margin-top: 10px;
                -webkit-border-radius: 7px;
                -moz-border-radius: 7px;
                border-radius: 7px;
                cursor: pointer;
                display: block;
                width: 245px;
                height: 12px;
                background-color: #e1e1e1;
                -moz-box-shadow: inset 0px 1px 1px #666;
                -webkit-box-shadow: inset 0px 1px 1px #666;
            }
        </style>
        <div id="wpsearch_synchronizing" >
            <input type="submit" class="button-primary" name="wpsearch_btn_index" value="Index All" id="wpsearch_btn_index"/>
            <input type="submit" class="button-primary" name="wpsearch_btn_deleteall" value="Delete All" /><span id="wpsearch_deleteall_status"></span>
            <div  id="progress_bar" style="display: none;">
                <div class="wpsearch_progress">
                    <div class="bar" style="display: none;"></div>
                </div>
            </div>
        </div>
        <div id="wpsearch_synchronize_error" style="display: none; color: red;">
            <b>There was an error during synchronization.</b><br/>
            If this problem persists, please email support@wpsear.ch and include any error message shown in the text box below, as well as the information listed in the WP Search Search Plugin Settings box above.</b><br/>
        <textarea id="wpsearch_error_text" style="width: 500px; height: 200px; margin-top: 20px;"></textarea>
        </div><?php
    }

endif;

/**
  Custom function for the callback validation referenced above
 * */
if (!function_exists('wpsearch_validate_api_key_callback')) {

    function wpsearch_validate_api_key_callback($field, $value, $existing_value) {
        $error = false;
        $options = array();
        $options['api_key'] = $value;
        $apiClient = new WPSearchApiClient($options);
        $ping = $apiClient->ping();
        if (!$ping) {
            $error = true;
            $value = $existing_value;
            $field['msg'] = "Wrong Api key";
        }

        $return['value'] = $value;
        if ($error == true) {
            $return['error'] = $field;
        }
        return $return;
    }

}
if (!function_exists('wpsearch_validate_exclude_post_ids_callback')) {

    function wpsearch_validate_exclude_post_ids_callback($field, $value, $existing_value) {
        $return['value'] = $value;

        if ($value != $existing_value && $value) {
            $ids = explode(",", $value);
            if ($ids) {
                $apiClient = new WPSearchApiClient();
                $apiClient->deleteByIds($ids);
            }
        }
        return $return;
    }

}