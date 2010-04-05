<?php

/**
 * 
 * Sesh: A memory-saving wrapper to Zend_Session_Namespace
 * 
 * Sesj uses the PHP Redis client Rediska to turn session 
 * variable naems into single-character ASCII-extended hashes. 
 * Each namespace therefore has 255 available attributes per session.
 * You can now now name session variables as verbosely as you'd like without 
 * worrying about additional memory consumption with high user load.  
 * This class will do the work switching out the full variable name
 * for the single-character attribute actually at work in the session.
 * 
 * @author Robert Elwell
 *
 */

class Sesh_Session_Namespace
{
    private $_namespace;
    private $_name;
    private $_namehash;
    
    function __construct($namespace)
    {
        $this->_name = $namespace;
        $this->_namehash = $this->transformNamespace($this->_name);
        $this->_namespace = new Zend_Session_Namespace($this->_namehash);
    }
    
    function __get($key)
    {
        $attr = $this->transform($key, true);
        return $this->_namespace->{$attr};
    }
    
    function __set($key, $value)
    {
        $attr = $this->transform($key);
        $this->_namespace->{$attr} = $value;
    }
    
    /**
     * Checks for an attribute of the namespace. Use this instead of isset().
     * @param $key
     * @return bool whether it's set
     */
    
    function hasKey($key)
    {
        $attr = $this->transform($key, true);
        return (($attr != null) && ($this->_namespace->{$attr} !== null));        
    }
    
    /**
     * Checks Redis to see if there is a character hash for this attribute in 
     * this namespace. If not, creates one, using the namespace's 'session characters' key.
     * 
     * 
     * @param string $varname
     * @return string a single ascii character
     */
    
    function transform($varname, $get=false)
    {
        $rediska = Rediska::getDefaultInstance();
        
        $val = $rediska->get("sesh_namespace_{$this->_name}_{$varname}");
        
        if ($val || ($get == true)) {
            return $val;
        } else {
            while ($rediska->get('sesh_locked') == 1) {
                // wait 1/20th of a second until we check again
                time_nanosleep(0, 50000000);
            }
            
            $rediska->set('sesh_locked', 1);
            
            $charInt = $rediska->get("sesh_namespacechrs_{$this->_name}");
            if ($charInt > 0){
                if ($charInt == 256){
                    throw new Exception('You can only use 255 attributes per namespace!');
                }
                $char = chr($charInt);
                $rediska->set("sesh_namespace_{$this->_name}_{$varname}", $char);
                $rediska->increment("sesh_namespacechrs_{$this->_name}", 1);
            } else {
                // chr(0) is NULL, hence starting with 1 
                $char = chr(1);
                $rediska->set("sesh_namespace_{$this->_name}_{$varname}", $char);
                $rediska->set("sesh_namespacechrs_{$this->_name}", 2);
            }
            
            $rediska->set('sesh_locked', 0);
            
            return $char;
        }
    }
    
    
    function transformNamespace($name)
    {
        $rediska = Rediska::getDefaultInstance();
        if ($val = $rediska->get("sesh_namespace_{$name}")) {
            return $val;
        } else {
            while ($rediska->get('sesh_locked') == 1) {
                // wait 1/20th of a second until we check again
                time_nanosleep(0, 50000000);
            }
            
            $rediska->set('sesh_locked', 1);
            
            $charInt = $rediska->get("sesh_namespacechrs");
            if ($charInt > 0){
                if ($charInt == 256){
                    throw new Exception('You can only use 255 attributes per namespace!');
                }
                $char = chr($charInt);
                $rediska->set("sesh_namespace_{$name}", $char);
                $rediska->increment("sesh_namespacechrs", 1);
            } else {
                // chr(0) is NULL, hence starting with 1
                $char = chr(1); 
                $rediska->set("sesh_namespace_{$name}", $char);
                $rediska->set("sesh_namespacechrs", 2);
            }
            
            $rediska->set('sesh_locked', 0);
            
            return $char;
        }
    }
}