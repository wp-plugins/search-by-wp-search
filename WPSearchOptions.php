<?php

class WPSearchOptions{
    
    public static function getOptions() {
        return get_option('wpsearch_settings');
    }

    public static function getOption($key) {
        $options = self::getOptions();
        if($options[$key]){
            return $options[$key];
        }
        return null;
    }
    
    public static function updateOptions($options) {
        update_option('wpsearch_settings', $options);
    }
    
    public static function updateOption($key,$val) {
        $options = self::getOptions();
        $options[$key] = $val;
        self::updateOptions($options);
    }
    
    
}

