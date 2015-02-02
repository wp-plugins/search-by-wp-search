<?php

class WPSearchApiClient {

    private $apiEndpoint = "wpsear.ch/api/1.0";

    public function __construct($options = array()) {
        $this->setParams($options);
    }

    public function setParams($options = array()) {
        $apiParams = array();
        if (isset($options['api_key']) && $options['api_key']) {
            $apiParams['api_key'] = $options['api_key'];
            $apiParams['use_https'] = $options['use_https'];
        } else {
            $options = WPSearchOptions::getOptions();
            $apiParams['api_key'] = $options['api_key'];
            $apiParams['use_https'] = $options['use_https'];
        }

        $this->host = $this->getHost($apiParams);


        return true;
    }

    private function getHost($apiParams) {
        $schema = "http";
        if ($apiParams['use_https']) {
            $schema = "https";
        }
        $host = $this->apiEndpoint . "/" . $apiParams['api_key'] . "/";

        return $schema . '://' . $host;
    }

    private function getURI($uri, $params) {
        $uri = $this->host . $uri;
        $uri = trim($uri, "/");

        if (isset($params) === true) {
            //$uri .= '?' . http_build_query($params);
        }

        return $uri;
    }

    public function performRequest($method, $uri, $params = null) {
        global $wpdb, $wp_version;
        $headers = array(
            'User-Agent' => 'WP Search Wordpress/' . $wp_version . ' Plugin/' . WPSEARCH_VERSION
        );

        $args = array(
            'method' => '',
            'timeout' => 10,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => $headers,
            'cookies' => array()
        );
        $params['site_domain'] = site_url();
        $url = $this->getURI($uri, $params);
        if ($method === 'GET' && isset($params) === true) {
            $method = 'POST';
        }
        $args['method'] = $method;
        if ('GET' == $method || 'DELETE' == $method) {
            $args['body'] = array();
        } else if ($method == 'POST') {
            $args['body'] = $params;
        }
        $args['timeout'] = 10;

        $logData = array('params' => json_encode($params),
            'url' => $url
        );
        $response = wp_remote_request($url, $args);
        if (!is_wp_error($response)) {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_message = wp_remote_retrieve_response_message($response);
            if ($response_code >= 200 && $response_code < 300) {
                $response_body = wp_remote_retrieve_body($response);
                $apiResponse = @json_decode($response_body, true);
                if ($apiResponse === null) {
                    $logData['error_message'] = "API return is not json :" . $response_body;
                    WPSearchUtils::saveErrorLog($logData);
                    return new WP_Error('wpsearch-api-error', __($logData['error_message'], 'wpsearch'));
                }
                $wpsearch_status_error = "";
                if ($apiResponse['status_error']) {
                    $wpsearch_status_error = $apiResponse['status_error'];
                }
                if ($uri != "") {//not ping
                    update_option('wpsearch_status_error', $wpsearch_status_error);
                }
                if (isset($apiResponse['error']) && $apiResponse['error']) {
                    $logData['error_message'] = $apiResponse['error']['message'];
                    WPSearchUtils::saveErrorLog($logData);
                    return new WP_Error('wpsearch-api-error', __($logData['error_message'], 'wpsearch'));
                }


                return $apiResponse;
            } elseif (!empty($response_message)) {
                $response_body = wp_remote_retrieve_body($response);
                $logData['error_message'] = $response_body . " : " . $response_code;
                WPSearchUtils::saveErrorLog($logData);
                return new WP_Error('wpsearch-api-error', __($logData['error_message'], 'wpsearch'));
            } else {
                $logData['error_message'] = 'Unknown Error ' . $response_code;
                WPSearchUtils::saveErrorLog($logData);
                return new WP_Error('wpsearch-api-error', __($logData['error_message'], 'wpsearch'));
            }
        } else {
            $logData['error_message'] = $response->get_error_message();
            WPSearchUtils::saveErrorLog($logData);
            return $response;
        }
    }

    public function ping() {

        $result = $this->performRequest('GET', '');
        if (is_wp_error($result) || $result['error']) {
            return false;
        }
        return true;
    }

    public function index($params) {
        return $this->performRequest('POST', 'index', array('document' => $params));
    }

    public function indexTerm($params) {
        return $this->performRequest('POST', 'indexterm', array('term' => $params));
    }

    public function deleteTerm($id) {
        return $this->performRequest('POST', 'deleteterm', array('id' => $id));
    }

    public function bulkIndex($documents) {
        return $this->performRequest('POST', 'bulkindex', array('documents' => $documents));
    }

    public function bulkIndexTerms($params) {
        return $this->performRequest('POST', 'bulkindexterms', array('terms' => $params));
    }

    public function structure($params) {
        return $this->performRequest('POST', 'structure', array('structure' => $params));
    }

    public function search($params) {
        return $this->performRequest('POST', 'search', array('params' => $params));
    }

    public function delete($id) {
        return $this->performRequest('POST', 'delete', array('id' => $id));
    }

    public function deleteByIds($ids) {
        return $this->performRequest('POST', 'deletebyids', array('ids' => $ids));
    }

    public function deleteAll() {
        return $this->performRequest('POST', 'deleteall');
    }

}
