<?php
/**
 * driver that uses php http:// stream to do requests
 *
 * Loosely Based on PEAR HTTP_Response
 *
 * @version $Revision: 1.52 $
 */
class PEAR2_HTTP_Request_Adapter_PhpStream extends PEAR2_HTTP_Request_Adapter 
{
    private $_phpErrorStr;

    /**
     * Throws exception if allow_url_fopen is off
     */
    public function __construct()
    {
        if (ini_get('allow_url_fopen') == false) {
            throw new PEAR2_HTTP_Request_Exception(
                'allow_url_fopen is off, the http:// stream wrapper will not function'
            );
        }
    }


    /**
     * Send the request
     *
     * This function sends the actual request to the
     * remote/local webserver using php streams.
     */
    public function sendRequest() 
    {

        // create context with proper junk
        $ctx = stream_context_create( 
            array(
                $this->uri->protocol => array(
                    'method' => $this->verb,
                    'content' => $this->body,
                    'header' => $this->buildHeaderString(),
                    'proxy'  => $this->proxy,
                )
            )
        );
        
        set_error_handler(array($this,'_errorHandler'));
        $fp = @fopen($this->uri->url, 'rb', false, $ctx);
        if (!$fp) {
            // php sucks
            if (strpos($this->_phpErrorStr, 'HTTP/1.1 304')) {
                restore_error_handler();
                $details = (array)$this->uri;

                $details['code'] = '304';
                $details['httpVersion'] = '1.1';

                return new PEAR2_HTTP_Request_Response($details,'',array(),array());
            }
            restore_error_handler();
            throw new PEAR2_HTTP_Request_Exception('Url ' . $this->uri->url . ' could not be opened');
        } else {
            restore_error_handler();
        }

        stream_set_timeout($fp, $this->requestTimeout);
        $body = stream_get_contents($fp);

        if ($body === false) {
            throw new PEAR2_HTTP_Request_Exception(
                'Url ' . $this->uri->url . ' did not return a response'
            );
        }

        $meta = stream_get_meta_data($fp);
        fclose($fp);

        $headers = $meta['wrapper_data'];

        $details = (array)$this->uri;

        $tmp = $this->parseResponseCode($headers[0]);
        $details['code'] = $tmp['code'];
        $details['httpVersion'] = $tmp['httpVersion'];

        $cookies = array();
        $this->headers = $this->cookies = array();

        foreach($headers as $line) {
            $this->processHeader($line);
        }

        return new PEAR2_HTTP_Request_Response($details,$body,$this->headers,$this->cookies);
    }

    /**
     * Buuild header String
     *
     * This method builds the header string
     * to be passed to the request.
     *
     * @return string $out  The headers
     */
    private function buildHeaderString() 
    {
        $out = '';
        foreach($this->headers as $header => $value) {
            $out .= "$header: $value\r\n";
        }
        return $out;
    }

    /**
     * This has to be public to be used as a callback but its actually private
     */
    public function _errorHandler($errno,$errstr) {
        $this->_phpErrorStr = $srrstr;
    }
}
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
?>
