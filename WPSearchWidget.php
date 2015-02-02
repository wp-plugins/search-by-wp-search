<?php

class WPSearchWidget extends WP_Widget {

    /**
     * Sets up the widgets name etc
     */
    public function __construct() {
        /* Widget settings. */
        $widget_ops = array('classname' => 'wpsearch_widget', 'description' => __( 'WP Search facets', 'wpsearch' ));

        /* Widget control settings. */
        $control_ops = array('width' => 250, 'height' => 350, 'id_base' => 'wpsearch_widget');

        /* Create the widget. */
        $this->WP_Widget('wpsearch_widget', __( 'WP Search facets', 'wpsearch' ), $widget_ops, $control_ops);
    }

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    function widget($args, $instance) {
        if (WPSearchUtils::isSearchPage()) {
            global $wpsearch;
            $response = $wpsearch->getFacetsRender();
            extract($args, EXTR_SKIP);
            echo $before_widget;

            if ($response['facets'] || $response['searchinput']) {
                echo $response['activefacets'];
                ?>
                <div class="wpsearch_search">
                    <?php echo $response['searchinput']; ?>
                </div>
                <?php
                echo $response['facets'];
            }

            echo $after_widget;
        }
    }

    /**
     * Outputs the options form on admin
     *
     * @param array $instance The widget options
     */
    public function form($instance) {
        // outputs the options form on admin
    }

    /**
     * Processing widget options on save
     *
     * @param array $new_instance The new options
     * @param array $old_instance The previous options
     */
    public function update($new_instance, $old_instance) {
        // processes widget options to be saved
    }

}
