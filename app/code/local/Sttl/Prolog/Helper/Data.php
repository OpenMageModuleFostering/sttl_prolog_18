<?php
/**
 * SilverTouch Technologies Limited.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.silvertouch.com/MagentoExtensions/LICENSE.txt
 *
 * @category   Sttl
 * @package    Sttl_Prolog
 * @copyright  Copyright (c) 2011 SilverTouch Technologies Limited. (http://www.silvertouch.com/MagentoExtensions)
 * @license    http://www.silvertouch.com/MagentoExtensions/LICENSE.txt
 */ 
 
class Sttl_Prolog_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_PROLOG_URL = 'prolog/general/prolog_url';
	const XML_PATH_PROLOG_USERNAME = 'prolog/general/prolog_username';	
	const XML_PATH_PROLOG_PASSWORD = 'prolog/general/prolog_password';
	const XML_PATH_PROLOG_CUSTOMERID = 'prolog/general/prolog_customerid';
	
	/**
	 * Get Prolog Url
	 * return string
	 */
	public function getPrologUrl()
	{
		return Mage::getStoreConfig(self::XML_PATH_PROLOG_URL);
	}
	
	/**
	 * Get Prolog Username
	 * return string
	 */
	public function getPrologUsername()
	{
		return Mage::getStoreConfig(self::XML_PATH_PROLOG_USERNAME);
	}
	
	/**
	 * Get Prolog Password
	 * return string
	 */
	public function getPrologPassword()
	{
		$password = Mage::helper('core')->decrypt(Mage::getStoreConfig(self::XML_PATH_PROLOG_PASSWORD));
		return $password;
	}
	
	/**
	 * Get Prolog Customer Id
	 * return string
	 */
	public function getPrologCustomerId()
	{
		return Mage::getStoreConfig(self::XML_PATH_PROLOG_CUSTOMERID);
	}
	
	public function xml2arrayNew($contents, $get_attributes=1, $priority = 'tag')
    {
        if(!$contents) return array();

        if(!function_exists('xml_parser_create')) {
            return array();
        }

        $parser = xml_parser_create('');
        xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); # http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, trim($contents), $xml_values);
        xml_parser_free($parser);

        if(!$xml_values) return;

        $xml_array = array();
        $parents = array();
        $opened_tags = array();
        $arr = array();

        $current = &$xml_array;

        $repeated_tag_index = array();
        foreach($xml_values as $data) {
            unset($attributes,$value);

            extract($data);

            $result = array();
            $attributes_data = array();
            
            if(isset($value)) {
                if($priority == 'tag') $result = $value;
                else $result['value'] = $value;
            }

            if(isset($attributes) and $get_attributes) {
                foreach($attributes as $attr => $val) {
                    if($priority == 'tag') $attributes_data[$attr] = $val;
                    else $result['attr'][$attr] = $val;
                }
            }

            if($type == "open") {
                $parent[$level-1] = &$current;
                if(!is_array($current) or (!in_array($tag, array_keys($current)))) {
                    $current[$tag] = $result;
                    if($attributes_data) $current[$tag. '_attr'] = $attributes_data;
                    $repeated_tag_index[$tag.'_'.$level] = 1;

                    $current = &$current[$tag];

                } else {

                    if(isset($current[$tag][0])) {
                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                        $repeated_tag_index[$tag.'_'.$level]++;
                    } else {
                        $current[$tag] = array($current[$tag],$result);
                        $repeated_tag_index[$tag.'_'.$level] = 2;
                        
                        if(isset($current[$tag.'_attr'])) {
                            $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                            unset($current[$tag.'_attr']);
                        }

                    }
                    $last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
                    $current = &$current[$tag][$last_item_index];
                }

            } elseif($type == "complete") {
                if(!isset($current[$tag])) {
                    $current[$tag] = $result;
                    $repeated_tag_index[$tag.'_'.$level] = 1;
                    if($priority == 'tag' and $attributes_data) $current[$tag. '_attr'] = $attributes_data;



                } else {
                    if(isset($current[$tag][0]) and is_array($current[$tag])) {

                        $current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
                        
                        if($priority == 'tag' and $get_attributes and $attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                        }
                        $repeated_tag_index[$tag.'_'.$level]++;

                    } else {
                        $current[$tag] = array($current[$tag],$result);
                        $repeated_tag_index[$tag.'_'.$level] = 1;
                        if($priority == 'tag' and $get_attributes) {
                            if(isset($current[$tag.'_attr'])) {
                                
                                $current[$tag]['0_attr'] = $current[$tag.'_attr'];
                                unset($current[$tag.'_attr']);
                            }
                            
                            if($attributes_data) {
                                $current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag.'_'.$level]++;
                    }
                }

            } elseif($type == 'close') {
                $current = &$parent[$level-1];
            }
        }
        
        return($xml_array);
    } 

    public function & func_array_path_new(&$array, $tag_path, $strict=false)
    {
        if (!is_array($array) || empty($array)) return false;

        if (empty($tag_path)) return $array;
        
        $path = explode('/',$tag_path);

        $elem =& $array; 

        foreach ($path as $key) {
            if (isset($elem[$key])) {
                $tmp_elem =& $elem[$key];
            }
            else {
                if (!$strict && isset($elem['#'][$key])) {
                    $tmp_elem =& $elem['#'][$key];
                }
                else if (!$strict && isset($elem[0]['#'][$key])) {
                    $tmp_elem =& $elem[0]['#'][$key];
                }
                else {
                    return false;
                }
            }

            unset($elem);
            $elem = $tmp_elem;
            unset($tmp_elem);
        }

        return $elem;
    }
}