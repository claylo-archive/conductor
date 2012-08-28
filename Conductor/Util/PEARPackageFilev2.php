<?php
namespace Conductor\Util;

class PEARPackageFilev2
{
    protected $xml = null;
    
    protected $info = array(
        'package_version' => '2.0',
        'name'          => null,
        'channel'       => null,
        'summary'       => null,
        'description'   => null,
        'lead'          => array(),
        'developer'     => array(),
        'contributor'   => array(),
        'helper'        => array(),
        'date'          => null,
        'version'       => array(
            'release'   => null,
            'api'       => null
        ),
        'stability'     => array(
            'release'   => null,
            'api'       => null
        ),
        'license'       => array(
            'type'      => null,
            'uri'       => null,
            'filesource'=> null,
        ),
        'notes'         => null,
        'contents'      => array(),
        'tasks'         => array(),
        'dependencies'  => array(
            'required'  => array(),
            'optional'  => array()
        ),
        'compatible'    => array(),
        'phprelease'    => array()
    );
    
    public function __construct($xml_string)
    {
        $this->xml = new \XMLReader();
        $this->xml->XML($xml_string);
    }
    
    public function parse()
    {
        $r = $this->xml;
        $r->read();
        if ($r->localName == 'package') {
            $version = $r->getAttribute('version');
        }
        if ($version != '2.0') {
            throw new \RuntimeException("can't read package versions other than 2.0");
        }
        
        
        while ($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT && $r->depth == 1) {
                
                switch ($r->localName) {
                    
                    case 'channel':
                    case 'uri':
                    case 'extends':
                    case 'name':
                    case 'summary':
                    case 'description':
                    case 'date':
                    case 'notes':                        
                        $this->info[$r->localName] = $this->_getValue();
                        break;
                    
                    case 'license':
                        $this->_parseLicense();
                        break;
                    
                    case 'lead':
                    case 'developer':
                    case 'contributor':
                    case 'helper':
                        $this->info[$r->localName][] = $this->_getPerson($r->localName);
                        break;
                    
                    case 'version':
                    case 'stability':
                        $this->_parseVersionAndStability($r->localName);
                        break;
                    
                    case 'contents':
                        $this->_parseContents();
                        break;
                        
                    case 'dependencies':
                        $this->_parseDeps();
                        break;
                    
                    // <extsrcrelease>, <extbinrelease> and <bundle> not 
                    // currently supported.
                    case 'phprelease':
                        $this->_parsePhpRelease();
                        break;
                        
                    default:
                        //...
                }
                
            }
            

        }
        return $this->info;
    }
    
    protected function _getValue()
    {
        $this->xml->read();
        $val = $this->xml->value;
        //$this->xml->read();
        return $val;
    }
    
    protected function _getPerson($element)
    {
        $person = array();
        $r = $this->xml;
        while($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT && $r->localName == 'name') {
                $person['name'] = $this->_getValue();
            }
            if ($r->nodeType == \XMLReader::ELEMENT && $r->localName == 'user') {
                $person['user'] = $this->_getValue();
            }
            if ($r->nodeType == \XMLReader::ELEMENT && $r->localName == 'email') {
                $person['email'] = $this->_getValue();
            }
            if ($r->nodeType == \XMLReader::ELEMENT && $r->localName == 'active') {
                $person['active'] = $this->_getValue();
            }
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == $element) {
                break;
            }
        }
        return $person;
    }
        
    protected function _parseVersionAndStability($element)
    {
        $r = $this->xml;
        while ($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT && $r->localName == 'release') {
                $this->info[$element]['release'] = $this->_getValue();
            }
            if ($r->nodeType == \XMLReader::ELEMENT && $r->localName == 'api') {
                $this->info[$element]['api'] = $this->_getValue();
            }
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == $element) {
                break;
            }
        }
    }
    
    protected function _parseLicense()
    {
        $r = $this->xml;
        if ($r->hasAttributes) {
            $this->info['license']['uri'] = $r->getAttribute('uri');
            $this->info['license']['filesource'] = $r->getAttribute('filesource');
        }
        $this->info['license']['type'] = $this->_getValue();
    }
    
    protected function _parseContents()
    {
        $contents = array();
        $path = array();
        $current_dir = null;
        $current_dir_depth = null;
        $current_file = null;
        $r = $this->xml;
        while ($r->read()) {
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == 'dir') {
                //echo "popping out of $current_dir\n";
                unset($path[$current_dir_depth]);
                $current_dir_depth = $current_dir_depth -1;
                if (isset($path[$current_dir_depth])) {
                    $current_dir = $path[$current_dir_depth];
                } else {
                    $current_dir = '/';
                }
            }
            
            if ($r->nodeType == \XMLReader::ELEMENT) {
                switch($r->localName) {
                    case 'dir':
                        $dirname = $r->getAttribute('name');
                        $baseinstalldir = $r->getAttribute('baseinstalldir');                    
                        //echo "dir {$dirname} at {$r->depth}\n";
                        $path[$r->depth] = $dirname;
                        $current_dir = $dirname;
                        $current_dir_depth = $r->depth;
                        break;
                    case 'file':
                        $filename = $r->getAttribute('name');
                        $role = $r->getAttribute('role');
                        $baseinstalldir = $r->getAttribute('baseinstalldir');
                        $md5sum = $r->getAttribute('md5sum');
                        $filepath = join('/', $path) . '/';
                        $filepath = str_replace('//', '/', $filepath);
                        $current_file = $filepath . $filename;
                        $this->info['contents'][$current_file] = array(
                            'role' => $role,
                            'baseinstalldir' => $baseinstalldir,
                            'md5sum' => $md5sum,
                            'name' => $filename
                        );
                        break;
                    default:
                        if ($r->prefix == 'tasks' && $r->namespaceURI == 'http://pear.php.net/dtd/tasks-1.0') {
                            $this->_parseTask($r->localName, $current_file);
                        }
                        //echo "default switch for {$r->localName} namespaceuri {$r->namespaceURI} prefix {$r->prefix}\n";
                }
            }
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == 'contents') {
                break;
            }            
        }
    }
    
    protected function _parseTask($task, $file)
    {
        if (! array_key_exists($file, $this->info['tasks'])) {
            $this->info['tasks'][$file] = array();
        }
        
        $attributes = array('task' => $task);
        $taskNS = 'http://pear.php.net/dtd/tasks-1.0';
        switch ($task) {
            case 'replace':
                $attributes['type'] = $this->xml->getAttribute('type');
                $attributes['from'] = $this->xml->getAttribute('from');
                $attributes['to'] = $this->xml->getAttribute('to');
                break;
            case 'windowseol':
                $attributes['convert_line_endings'] = "\r\n";
                break;
            case 'unixeol':
                $attributes['convert_line_endings'] = "\n";
                break;
            default:
                // we only handle basic tasks at this point.
        }
        $this->info['tasks'][$file][] = $attributes;
    }
    
    /**
     * Optional Dependency Groups not supported right now
     * 
     */
    protected function _parseDeps()
    {
        $r = $this->xml;
        
        // required or optional
        $dep_type = null;
        
        while ($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT) {
                switch ($r->localName) {
                    case 'optional':
                    case 'required':
                        $dep_type = $r->localName;
                        break;
                        
                    case 'php':
                        $this->_parseDepPhp($dep_type);
                        break;

                    case 'pearinstaller':
                        $this->_parseDepPEARInstaller($dep_type);
                        break;
                        
                    case 'package':
                    case 'subpackage':
                        $this->_parseDepPackage($dep_type, $r->localName);
                        break;
                    
                    case 'extension':
                        $this->_parseDepExtension($dep_type);
                        break;

                    case 'os':
                        $this->_parseDepOs($dep_type);
                        break;

                    case 'arch':
                        $this->_parseDepArch($dep_type);
                        break;
                                            
                    default:
                        // not handling dependency groups presently
                }
            }
                        
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == 'dependencies') {
                break;
            }
        }
    }
    
    protected function _parseDepPhp($dep_type)
    {
        if ($dep_type == 'installconditions') {
            $dep_or_installcondition = $dep_type;
        } else {
            $dep_or_installcondition = 'dep';
        }
        
        $r = $this->xml;
        $php = array(
            "$dep_or_installcondition" => 'php',
            'min' => null,
            'max' => null,
            'recommended' => null,
            'exclude' => null
        );
        
        while ($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT) {
                if ($r->localName == 'exclude') {
                    if ($php['exclude'] === null) {
                        $php['exclude'] = array();
                    }
                    $php['exclude'][] = $this->_getValue();
                } else {
                    $php[$r->localName] = $this->_getValue();
                }
            }
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == 'php') {
                break;
            }
        }
        
        if ($dep_or_installcondition == 'dep') {
            $this->info['dependencies'][$dep_type][] = $php;
        } else {
            return $php;
        }
    }

    protected function _parseDepPEARInstaller($dep_type)
    {
        $r = $this->xml;
        $pear = array(
            'dep' => 'pearinstaller',
            'min' => null,
            'max' => null,
            'recommended' => null,
            'exclude' => null
        );
        
        while ($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT) {
                if ($r->localName == 'exclude') {
                    if ($pear['exclude'] === null) {
                        $pear['exclude'] = array();
                    }
                    $pear['exclude'][] = $this->_getValue();
                } else {
                    $pear[$r->localName] = $this->_getValue();
                }
            }
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == 'pearinstaller') {
                break;
            }
        }
        $this->info['dependencies'][$dep_type][] = $pear;
    }
    
    protected function _parseDepPackage($dep_type, $pkg_type)
    {
        $r = $this->xml;
        $elements = array(
            'dep' => $pkg_type,
            'name' => null,
            'channel' => null,
            'uri' => null,
            'min' => null,
            'max' => null,
            'recommended' => null,
            'exclude' => null,
            'conflicts' => null,
            'providesextension' => null
        );

        while ($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT) {
                if ($r->localName == 'exclude') {
                    if ($elements['exclude'] === null) {
                        $elements['exclude'] = array();
                    }
                    $elements['exclude'][] = $this->_getValue();
                } elseif ($r->localName == 'conflicts') {
                    $elements['conflicts'] = true;
                } else {
                    $elements[$r->localName] = $this->_getValue();
                }
            }
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == $pkg_type) {
                break;
            }
        }
        $this->info['dependencies'][$dep_type][] = $elements;
    }

    protected function _parseDepExtension($dep_type)
    {
        if ($dep_type == 'installconditions') {
            $dep_or_installcondition = $dep_type;
        } else {
            $dep_or_installcondition = 'dep';
        }
        
        $r = $this->xml;
        $elements = array(
            "$dep_or_installcondition" => 'extension',
            'name' => null,
            'min' => null,
            'max' => null,
            'recommended' => null,
            'exclude' => null,
            'conflicts' => null
        );

        while ($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT) {
                if ($r->localName == 'exclude') {
                    if ($elements['exclude'] === null) {
                        $elements['exclude'] = array();
                    }
                    $elements['exclude'][] = $this->_getValue();
                } elseif ($r->localName == 'conflicts') {
                    $elements['conflicts'] = true;
                } else {
                    $elements[$r->localName] = $this->_getValue();
                }
            }
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == 'extension') {
                break;
            }
        }
        
        if ($dep_or_installcondition == 'dep') {
            $this->info['dependencies'][$dep_type][] = $elements;
        } else {
            return $elements;
        }
    }

    protected function _parseDepOs($dep_type)
    {
        if ($dep_type == 'installconditions') {
            $dep_or_installcondition = $dep_type;
        } else {
            $dep_or_installcondition = 'dep';
        }

        $r = $this->xml;
        $elements = array(
            "$dep_or_installcondition" => 'os',
            'name' => null,
            'conflicts' => null
        );

        while ($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT) {
                if ($r->localName == 'conflicts') {
                    $elements['conflicts'] = true;
                } else {
                    $elements[$r->localName] = $this->_getValue();
                }
            }
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == 'os') {
                break;
            }
        }
        
        if ($dep_or_installcondition == 'dep') {
            $this->info['dependencies'][$dep_type][] = $elements;
        } else {
            return $elements;
        }
    }

    protected function _parseDepArch($dep_type)
    {
        if ($dep_type == 'installconditions') {
            $dep_or_installcondition = $dep_type;
        } else {
            $dep_or_installcondition = 'dep';
        }

        $r = $this->xml;
        $elements = array(
            "$dep_or_installcondition" => 'arch',
            'pattern' => null,
            'conflicts' => null
        );

        while ($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT) {
                if ($r->localName == 'conflicts') {
                    $elements['conflicts'] = true;
                } else {
                    $elements[$r->localName] = $this->_getValue();
                }
            }
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == 'arch') {
                break;
            }
        }
        
        if ($dep_or_installcondition == 'dep') {
            $this->info['dependencies'][$dep_type][] = $elements;
        } else {
            return $elements;
        }
    }
    
    protected function _parsePhpRelease()
    {
        $ic = 'installconditions'; // so ... many ... letters ...
        $rel = array(
            'installconditions' => array(),
            'filelist' => array(
                'installas' => array(),
                'ignore' => array()
            )
        );

        $r = $this->xml;
        while ($r->read()) {
            if ($r->nodeType == \XMLReader::ELEMENT && $r->localName == $ic) {
                while ($r->read()) {
                    if ($r->nodeType == \XMLReader::ELEMENT) {
                        switch ($r->localName) {
                            case 'php':
                                $php = $this->_parseDepPhp($ic);
                                unset($php[$ic]);
                                $rel[$ic]['php'] = $php;
                                break;
                            case 'os':
                                $os = $this->_parseDepOs($ic);
                                unset($os[$ic]);
                                $rel[$ic]['os'] = $os;
                                break;
                            case 'arch':
                                $arch = $this->_parseDepArch($ic);
                                unset($arch[$ic]);
                                $rel[$ic]['arch'] = $arch;
                                break;
                            case 'extension':
                                $ext = $this->_parseDepExtension($ic);
                                unset($ext[$ic]);
                                if (! array_key_exists('extension', $rel[$ic])) {
                                    $rel[$ic]['extension'] = array();
                                }
                                $rel[$ic]['extension'] = $ext;
                                break;
                            default:
                                // nothing else we care about
                        }
                    }
                    if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == $ic) {
                        break;
                    }
                }
            }
            if ($r->nodeType == \XMLReader::ELEMENT && $r->localName == 'filelist') {
                while ($r->read()) {
                    if ($r->nodeType == \XMLReader::ELEMENT && $r->localName == 'install') {
                        $as = $r->getAttribute('as');
                        $name = $r->getAttribute('name');
                        if (! empty($as) && ! empty($name)) {
                            $rel['filelist']['installas'][$name] = $as;
                        }
                    }
                    if ($r->nodeType == \XMLReader::ELEMENT && $r->localName == 'ignore') {
                        $name = $r->getAttribute('name');
                        if (! empty($name)) {
                            $rel['filelist']['ignore'] = $name;
                        }
                    }
                    if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == 'filelist') {
                        break;
                    }                                
                }
            }
            if ($r->nodeType == \XMLReader::END_ELEMENT && $r->localName == 'phprelease') {
                break;
            }            
        }
        $this->info['phprelease'][] = $rel;
    }
}