<?php

if (!function_exists('wpsearch_get_results')) {

    /**
     * Return the WP Search search results object.
     *
     * Use this after a search has been executed to get access to the raw results
     * from WP Search. This allows you to access facets and other result metadata.
     *
     * @return Array An array or NULL if no search has been executed.
     */
    function wpsearch_get_results() {
        global $wpsearch;

        return $wpsearch->getResults();
    }

    /**
     * Return the WP Search search results object.
     *
     * Use this to perform search on WP Seacrh API.
     *
     * @param Array $params . Array of params to perform search.
     *
     * @return Array An array or NULL if no search has been executed.
     */
    function wpsearch_get_results_from_api($params) {
        global $wpsearch;

        return $wpsearch->getResultsFromApi($params);
    }

    /**
     * Return the WP Search search params of latest query or default params.
     *
     * Use this after a search has been executed to get search params of runned query.
     * This allows you to access search params metadata .
     *
     * @global WPSearchPlugin $wpsearch The WPSearchPlugin instance.
     *
     * @return Array Array of search params.
     */
    function wpsearch_get_search_params() {
        global $wpsearch;

        return $wpsearch->getSearchParams();
    }

    /**
     * Render the WP Search search results and facets.
     *
     * Use this to render search results page and facets .
     *
     * @param Array $params . Array of params to perform search.
     *
     * @return void
     */
    function wpsearch_do_search($params) {
        global $wpsearch;

        return $wpsearch->doSearch($params);
    }

    /**
     * Normalaise params for run search query.
     *
     * Use this to normalaise yur search params before run search query with wpsearch_get_results_from_api fiunction.
     *
     * @param Array $params . Array of params to perform search.
     *
     * @return Array of normalaised search params.
     */
    function wpsearch_normalaise_search_params($params) {
        global $wpsearch;

        return $wpsearch->normaliseSearchParams($params);
    }

    /**
     * Render results and facets.
     *
     * Use this to render results and facets of latest query or run new query.
     *
     * @param Array $results Optional. Array of results and facets to render.If null will be used last query results,if no last query will run search using $params .
     * @param Array $params Optional. Array of search params to use for render.If $results is null and no previous search query will run search using this field.
     *
     * @return void.
     */
    function wpsearch_render($results = null, $params = null) {
        global $wpsearch;

        return $wpsearch->render($results, $params);
    }

   /**
     * Get results render.
     *
     * Use this to render results and facets of latest query or run new query.
     *
     * @param Array $results Optional. Array of results and facets to render.If null will be used last query results,if no last query will run search using $params .
     * @param Array $params Optional. Array of search params to use for render.If $results is null and no previous search query will run search using this field.
     *
     * @return Array of results render.
     */
    function wpsearch_get_results_render($results = null, $params = null) {
        global $wpsearch;

        return $wpsearch->getResultsRender($results, $params);
    }

    /**
     * Get facest render.
     *
     * Use this to render results and facets of latest query or run new query.
     *
     * @param Array $results Optional. Array of results and facets to render.If null will be used last query results,if no last query will run search using $params .
     * @param Array $params Optional. Array of search params to use for render.If $results is null and no previous search query will run search using this field.
     *
     * @return Array of facets render.
     */
    function wpsearch_get_facets_render($results = null, $params = null) {
        global $wpsearch;

        return $wpsearch->getFacetsRender($results, $params);
    }

    /**
     * Delete all posts from WP Search .
     *
     * Use this to delete all posts from WP Search.
     *
     * @return Boolean .
     */
    function wpsearch_deleteall() {
        global $wpsearch;

        return $wpsearch->deleteAll();
    }

    /**
     * Index all posts from WP Search .
     *
     * Use this to index all posts to WP Search.
     *
     * @return true or false.
     */
    function wpsearch_indexall() {
        global $wpsearch;

        return $wpsearch->indexAll();
    }
    
    /**
     * Index post to WP Search.
     *
     * Use this to manually index post into WP Search.
     *
     * @param int|WP_Post $post  . Post ID or post object.
     * 
     * @return Array of facets render.
     */
    function wpsearch_index_post($post) {
        global $wpsearch;

        return $wpsearch->getWPSearchIndexer()->handleSave($post);
    }
    /**
     * Delete post from WP Search.
     *
     * Use this to delete post from WP Search.
     *
     * @param int $id  . Post ID .
     * 
     * @return void.
     */
    function wpsearch_delete_post($id) {
        global $wpsearch;

        return $wpsearch->getWPSearchIndexer()->handleDelete($id);
    }

    /**
     * Return the WP Search options array.
     *
     * Get WP Search options .
     *
     * @return Array An array of options.
     */
    function wpsearch_get_options() {
        return WPSearchOptions::getOptions();
    }

}
?>