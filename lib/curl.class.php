<?php

class curl {

        private $options = array();
        private $result;
        private $debug = false;

        // url to grab
        public function url($url){
                $this->option(CURLOPT_URL, $url);
                if(substr($url, 0, 5) == 'https'){
                    $this->option(CURLOPT_SSL_VERIFYPEER, FALSE);
            $this->option(CURLOPT_SSL_VERIFYHOST,  1);
                }
        }

        // misc options that dont have their own method
        public function option($constant, $value){
                $this->options[$constant] = $value;
        }

        // set misc options with an array
        public function options($arr){
                if(is_array($arr)){
                        foreach($arr as $key => $value){
                                $this->option($key, $value);
                        }
                }else{
                        return false;
                }
        }

        // turn on debugging
        public function debug(){
                $this->debug = true;
        }

        // default options
        private function default_options(){
                if($this->debug === true){
                        $default[CURLOPT_HEADER] = true; // display headers for debugging
                }else{
                       $default[CURLOPT_HEADER] = false; // dont display headers
                }
                $default[CURLOPT_FOLLOWLOCATION] = true; // follow Location: headers
                $default[CURLOPT_RETURNTRANSFER] = true; // dont echo the result, return it
                $default[CURLOPT_AUTOREFERER] = true; // auto set referrer on Location: headers
                $this->options($default);
        }

        public function go(){
                $resource = curl_init();
                $this->default_options();
                curl_setopt_array($resource, $this->options);
                $this->result = curl_exec($resource);
                curl_close($resource);
        }

        public function result(){
                return $this->result;
        }

        // submit a form
        public function form($arr){
        $this->option(CURLOPT_POST, true);
        $this->option(CURLOPT_POSTFIELDS, $arr);
        }

        public function cookiejar($file){
                $this->option(CURLOPT_COOKIEJAR, $file);
        }
        public function json($data_string){
            $this->option(CURLOPT_CUSTOMREQUEST, "POST");
            $this->option(CURLOPT_POSTFIELDS, $data_string);
            $this->option(CURLOPT_RETURNTRANSFER, true);
            $this->option(CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
            );
        }
}

?>