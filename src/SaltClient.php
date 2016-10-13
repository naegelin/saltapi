<?php
namespace SaltApi;

/**
 * Class SaltClient
 *
 * This is a PHP client for talking to the restful endpoint of Salt Stack
 *
 * (c) 2014 Chris Naegelin <n---lin@gmail.com>
 * (c) 2016 Gregory Boddin <gregory@siwhine.net>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
 * associated documentation files (the "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following
 * conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the
 * Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 *
 * $salt = new SaltClient('my.saltapi.com','443','saltapi','password');
 * $results = $salt->run('*','test.ping');
 * $results = $salt->jobs($results->jid);
 *
 * @package SaltApi
 */
class SaltClient
{
    /**
     * @var bool $debug Should be set to true in constructor if you wish to see more output
     */
    private $debug = false;
    /**
     * @var $token string Our auth token
     */
    private $token;
    /**
     * @var $expire int Internal token expiration tracking
     */
    private $expire;
    /**
     * @var string $baseurl Constructed url for API endpoint, SSL required
     */
    private $baseurl;
    /**
     * @var string $async Should we call async - this is needed if you want to get back a job id
     */
    private $async;
    /**
     * @var boolean $sslCheck Should SSL checks be performed
     */
    private $sslCheck = true;


    /**
     * SaltClient constructor.
     * @param string $hostname
     * @param string $port
     * @param string $username
     * @param string $password
     * @param string $authtype
     * @param bool $async
     */
    public function __construct(
        $hostname,
        $port,
        $username,
        $password,
        $authtype = 'pam',
        $async = true,
        $sslCheck = true,
        $debug = false
    ) {
    
        $this->baseurl = 'https://' . $hostname . ':' . $port . '/';
        $this->async = ($async == true ? 'local_async' : 'local');
        $this->debug = $debug;
        $this->sslCheck = $sslCheck;
        $credentials = $this->authenticate($username, $password, $authtype);
        $this->token = $credentials->token;
        $this->expire = $credentials->expire;
    }


    /**
     * @param $username
     * @param $password
     * @param $authtype
     * @return mixed
     * @throws \Exception
     */
    private function authenticate($username, $password, $authtype)
    {

        $url = $this->baseurl . 'login';

        $fields_string = "";

        $fields = array(
            'username' => urlencode($username), 'password' => urlencode($password), 'eauth' => urlencode($authtype)
        );

        //url-ify the data for the POST

        foreach ($fields as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
        }

        rtrim($fields_string, '&');


        //open connection
        $ch = curl_init();

        //set the CURL optioins
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_VERBOSE, $this->debug);
        //security vulnerability:
        if($this->sslCheck) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        //we want to receive the body response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //This one is important because we want a nice JSON response
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'Content-Length: ' . strlen($fields_string)));

        //execute post
        $result = curl_exec($ch);

        //close connection
        curl_close($ch);


        $jsonResults = json_decode($result);
        if (empty($jsonResults)) {
            throw new \Exception('Bad response from the server!');
        }

    //return $jsonResults->return[0]->token;
        return $jsonResults->return[0];
    }

    public function run($targethosts, $command, $argument = "")
    {
        $url = $this->baseurl;


        $dataobj = new \stdClass();
        $dataobj->client = $this->async;
        $dataobj->tgt = $targethosts;
        $dataobj->fun = $command;

        //Passing an empty arg gets mis-interpreted as an argument so don't send it at all if we dont have any.
        if ($argument != "") {
            $dataobj->arg = $argument;
        }


        $fields_string = '[' . json_encode($dataobj) . ']';


        //open connection
        $ch = curl_init();

        //set the CURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_VERBOSE, $this->debug);
        //security vulnerability - use this only if you are testing against a self-signed cert:
        if($this->sslCheck) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        //we want to receive the body response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //This one is important because we want a nice JSON response
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'X-Auth-Token: ' . $this->token,
                'Content-Type: application/json'));

        //execute post
        $result = curl_exec($ch);

        //close connection
        curl_close($ch);


        $jsonResults = json_decode($result);
        if (empty($jsonResults)) {
            throw new \Exception('Bad response from the server!');
        }


        return $jsonResults->return[0];
    }
    //end run


    public function jobs($jobid)
    {
        $url = $this->baseurl . "jobs/$jobid";


        //open connection
        $ch = curl_init();

        //set the CURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_VERBOSE, $this->debug);
        //security vulnerability - use this only if you are testing against a self-signed cert:
        if($this->sslCheck) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        //we want to receive the body response
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //This one is important because we want a nice JSON response
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Accept: application/json',
                'X-Auth-Token: ' . $this->token));

        //execute post
        $result = curl_exec($ch);


        //close connection
        curl_close($ch);


        $jsonResults = json_decode($result);
        if (empty($jsonResults)) {
            throw new \Exception('Bad response from the server!');
        }

        return $jsonResults;
    }
}
