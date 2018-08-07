#!/usr/bin/php
<?php

$config = [
	"op5" => [
		"list_query" => [
			[
				"userpwd" => 'api$Default:api',
				"host" => 'https://YOUR.op5.URL',
				"api" => '/api/filter/query?format=json&query=',
				"host_filters" => [
					"demo" => [
						"filter" => '[hosts] name ~~ "demo*"',
						"columns" => [ 
							"NAME" => "name",
							"ADDRESS" => "address"
						],
						"host_vars" => [
							"ansible_port" => [22,22022],
							"ansible_host" => "ADDRESS"
						],
						"limit" => null,
						"offset" => null,
						"group_vars" => [],
						"check_ansible_port" => true
					]
				],				
			]
		],
		"host_query" => [
			[
				"userpwd" => 'api$Default:api',
				"host" => 'https://YOUR.op5.URL',
				"api" => '/api/filter/query?format=json&query=',
				"filter" => '[hosts]name= {host}',
                "columns" => [ 
                    "NAME" => "name",
                    "ADDRESS" => "address"
				],
                "host_vars" => [
                    "ansible_port" => [22,22022],
                    "ansible_host" => "ADDRESS"
				],
				"check_ansible_port" => true
			]
		]
	],
];

const CONFIG_FILE = 'config.json';
const DEFAULT_STATIC_FILE = 'op5_hosts.ansible';

/**
 * Get a host list from op5 as JSON
 * 
 * @return array
 */
function get_host_list() {
	global $config;

	$allHosts = array();
	$i = 0;
	foreach($config["op5"]["list_query"] as $listQueries) {
		$allHosts[$i] = array();
		foreach($listQueries["host_filters"] as $filterName => $filter) {
			$call =  $filter["filter"];

			$columns = "";

			if(array_key_exists("columns", $filter) && is_array($filter["columns"]) && count($filter["columns"]) > 0) {
				$columns = "&columns=";
				foreach($filter["columns"] as $key => $name) {
					$columns .= $name . ",";
				}
				$columns = substr($columns, 0, -1);
			}

			$call .= $columns;

			$call .= array_key_exists("limit", $filter) && is_int($filter["limit"]) && $filter["limit"] > 0 ? "&limit=" . $filter["limit"] : "";
			$call .= array_key_exists("offset", $filter) && is_int($filter["offset"] && $filter["offset"] > 0) ? "&offset=" . $filter["offset"] : "";

			$data = do_curl_call($listQueries["host"] . $listQueries["api"] . $call, $listQueries["userpwd"]);

			if($data) { 
				$allHosts[$i][$filterName] = $data;
			}
		}
		$i++;
	}

	return $allHosts;
}

function get_host_list_from_group() {
	global $config;

	$allHosts = array();
	$i = 0;
	foreach($config["op5"]["list_query"] as $listQueries) {
		$allHosts[$i] = array();
		foreach($listQueries["group_filters"] as $filterName => $filter) {
			$call =  $filter["filter"];

			$data = do_curl_call($listQueries["host"] . $listQueries["api"] . $call, $listQueries["userpwd"]);
			
			foreach($data as $group) {
				$host_call = $listQueries["host"] . $listQueries["api"] . "[hosts] groups >=" . "\"" . $group["name"] . "\"";

				$columns = "";

				if(array_key_exists("columns", $filter) && is_array($filter["columns"]) && count($filter["columns"]) > 0) {
					$columns = "&columns=";
					foreach($filter["columns"] as $key => $name) {
						$columns .= $name . ",";
					}
					$columns = substr($columns, 0, -1);
				}
				
				$host_call .= $columns;
				
				if(array_key_exists("limit", $filter) && is_int($filter["limit"]) && $filter["limit"] > 0) {
					$host_call .= "&limit=" . $filter["limit"];
				} elseif((!array_key_exists("limit", $filter) || (array_key_exists("limit", $filter) && !$filter["limit"])) && $group["num_hosts"] > 0) {
					$host_call .= "&limit=" . $group["num_hosts"];
				}
				
				$host_call .= array_key_exists("offset", $filter) && is_int($filter["offset"]) && $filter["offset"] > 0 ? "&offset=" . $filter["offset"] : "";

				$hosts = do_curl_call($host_call, $listQueries["userpwd"]);

				if($hosts) $allHosts[$i][$filterName][$group["name"]] = $hosts;
			}
		}
		$i++;
	}

	return $allHosts;
}

/**
 * Get a host from op5 as JSON
 * 
 * @param string $hostName
 * 
 * @return array
 */
function get_host($hostName) {
	global $config;

	$host = array();

	foreach($config["op5"]["host_query"] as $index => $hostQueries) {
		$call =  $hostQueries["filter"];

		$columns = "";

		if(is_array($hostQueries["columns"]) && count($hostQueries["columns"]) > 0) {
			$columns = "&columns=";
			foreach($hostQueries["columns"] as $key => $name) {
				$columns .= $name . ",";
			}
			$columns = substr($columns, 0, -1);
		}

		$call .= $columns;

		$call = str_replace("{host}", '"' . $hostName . '"' , $call);
		$a_handle = curl_init($hostQueries["host"] . $hostQueries["api"] . $call);

		$data = do_curl_call($hostQueries["host"] . $hostQueries["api"] . $call, $hostQueries["userpwd"]);

		if($data) {
			$host[$index] = $data;
			break; 
		}
	}

	return $host;
}

/**
 * Do curl call
 * 
 * @param string $call
 * @param string $userpass
 * 
 * @return array
 */
function do_curl_call($call, $userpass) {
	global $verbose;

	$call = str_replace(" ", "%20", $call);

	if($verbose) echo $call . "\n";

	$a_handle = curl_init($call);
	curl_setopt($a_handle, CURLOPT_USERPWD, $userpass);
	curl_setopt($a_handle, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($a_handle, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	curl_setopt($a_handle, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($a_handle, CURLOPT_SSL_VERIFYHOST, false);

	$result = curl_exec($a_handle);
	if($verbose && !$result) echo curl_error($a_handle) . "\n";
	$data = json_decode($result, true);
	if ($verbose && $result && array_key_exists("error", $data)) var_dump($result);

	curl_close($a_handle);

	return !array_key_exists("error", $data) ? $data : null;
}

/**
 * Filter host and remove all inactive host from array and add get connection port
 * 
 * @param array $hosts
 * 
 * @return array
 */
function filter_active_hosts($hosts, $config_type) {
	global $config;
	
	foreach($hosts as $key => $host) {
		foreach($host as $filterName => $filter) {
			if(array_key_exists("check_ansible_port", get_list_query_filter($filterName, $key, $config_type)) && get_list_query_filter($filterName, $key, $config_type)["check_ansible_port"] === true) {
				if(array_key_exists("ansible_port", get_list_query_filter($filterName, $key, $config_type)["host_vars"])) {
					if(is_array(get_list_query_filter($filterName, $key, $config_type)["host_vars"]["ansible_port"])) {
						$ports = get_list_query_filter($filterName, $key, $config_type)["host_vars"]["ansible_port"];
					} else {
						$ports = array((int)get_list_query_filter($filterName, $key, $config_type)["host_vars"]["ansible_port"]);
					}
				} else {
					$ports = array(22);
				}
				$filter_hosts = array();
				if($filter) {
					for($i = 0; $i < count($filter); $i++) {
						$port = get_ansible_port($filter[$i], $ports);

						if($port) {
							$filter[$i]['ansible_port'] = $port;
							array_push($filter_hosts, $filter[$i]);
						}
					}
				}
				$hosts[$key][$filterName] = $filter_hosts;
			}		
		}
	}

	return $hosts;
}

/**
 * Filter host and remove all inactive host from array and add get connection port
 * 
 * @param array $hosts
 * 
 * @return array
 */
function filter_active_group_hosts($hosts, $config_type) {
	global $config, $verbose;
	
	foreach($hosts as $key => $host) {
		foreach($host as $groupIndex => $groupValue) {
			foreach($groupValue as $filterName => $filter) {
				if(array_key_exists("check_ansible_port", get_list_query_filter($groupIndex, $key, $config_type)) && get_list_query_filter($groupIndex, $key, $config_type)["check_ansible_port"] === true) {
					if(array_key_exists("ansible_port", get_list_query_filter($groupIndex, $key, $config_type)["host_vars"])) {
						if(is_array(get_list_query_filter($groupIndex, $key, $config_type)["host_vars"]["ansible_port"])) {
							$ports = get_list_query_filter($groupIndex, $key, $config_type)["host_vars"]["ansible_port"];
						} else {
							$ports = array((int)get_list_query_filter($groupIndex, $key, $config_type)["host_vars"]["ansible_port"]);
						}
					} else {
						$ports = array(22);
					}
					$filter_hosts = array();
					if($filter) {
						for($i = 0; $i < count($filter); $i++) {
							$port = get_ansible_port($filter[$i], $ports);

							if($port) {
								$filter[$i]['ansible_port'] = $port;
								array_push($filter_hosts, $filter[$i]);
							}
						}
					}
					$hosts[$key][$groupIndex][$filterName] = $filter_hosts;
				}		
			}
		}
	}

	return $hosts;
}

/**
 * Filter host and remove all inactive host from array and add get connection port
 * 
 * @param array $host
 * 
 * @return array
 */
function is_host_active($hosts) {
	global $config;

	foreach($hosts as $key => $host) {
		if(array_key_exists("check_ansible_port", $config["op5"]["host_query"][$key]) && $config["op5"]["host_query"][$key]["check_ansible_port"] === true) {
			if(array_key_exists("ansible_port", $config["op5"]["host_query"][$key]["host_vars"])) {
				if(is_array($config["op5"]["host_query"][$key]["host_vars"]["ansible_port"])) {
					$ports = $config["op5"]["host_query"][$key]["host_vars"]["ansible_port"];
				} else {
					$ports = array((int)$config["op5"]["host_query"][$key]["host_vars"]["ansible_port"]);
				}
			} else {
				$ports = array(22);
			}

			$port = get_ansible_port($host[0], $ports);

			if($port) {
				$hosts[$key][0]['ansible_port'] = $port;
			} else {
				$hosts[$key][0] = array();
			}
		}
	}
	
	return $hosts;
}

/**
 * Get active ansible port
 * 
 * @param array $host
 * 
 * @return int
 */
function get_ansible_port($host, $ports) {
	global $verbose;

	if($verbose) echo "\n# Testing ansible ssh port on host " . $host['name'] . " at " . $host['address'];
	
	foreach($ports as $port) {
		if ($fp = @fsockopen($host['address'], $port, $errno, $errstr, 1)) { 
			fclose($fp);
			if($verbose) echo "\n" . $port . " OK";
			return $port;					
		} else {
			if($verbose) echo "\n" . $port . " no";
		}
	}
	if($verbose) echo "\nCould not find any open ssh port for ansible";
	return null;
}

/**
 * Parse op5 host list to a ansible json string
 * 
 * @param array $hosts
 * 
 * @return string
 */
function parse_host_list_to_ansible($hosts, $config_type) {	
	$main = array("_meta" => array("hostvars" => array()));

	foreach($hosts as $index => $group) {
		foreach($group as $filterName => $filter) {
			$main[$filterName]["hosts"] = array();

			$host_config = get_list_query_filter($filterName, $index, $config_type);

			foreach($filter as $host) {
				array_push($main[$filterName]["hosts"], $host['name']);
				$main["_meta"]["hostvars"][$host['name']] = parse_host_vars($host, 
					$host_config["host_vars"], 
					$host_config["columns"]
				);
			}
			if(array_key_exists("group_vars", $host_config) && count($host_config["group_vars"]) > 0) {
				$main[$filterName]["vars"] = $host_config["group_vars"];
			}

			if(array_key_exists("children", $host_config)) {
				$main[$filterName]["children"] = $host_config["children"];
			}	
		}
	}

	return $main;
}

/**
 * Parse op5 host list to a ansible json string
 * 
 * @param array $hosts
 * 
 * @return string
 */
function parse_group_list_to_ansible($hosts, $config_type) {
	global $config;

	$main = array("_meta" => array("hostvars" => array()));
	foreach($hosts as $index => $group) {
		foreach($group as $groupIndex => $groupValue) {

			$host_config = get_list_query_filter($groupIndex, $index, $config_type);

			foreach($groupValue as $filterName => $filter) {
				$main[$filterName]["hosts"] = array();

				foreach($filter as $host) {
					array_push($main[$filterName]["hosts"], $host['name']);
					$main["_meta"]["hostvars"][$host['name']] = parse_host_vars($host, 
						$host_config["host_vars"], 
						$host_config["columns"]
					);
				}
				if(array_key_exists("group_vars", $host_config) && count($host_config["group_vars"]) > 0) {
					$main[$filterName]["vars"] = $host_config["group_vars"];
				}

				if(array_key_exists("children", $host_config)) {
					$main[$filterName]["children"] = $host_config["children"];
				}				
			}
		}
	}

	return $main;
}

/**
 * Get the list query from index
 * 
 * @param int $index
 * 
 * @return array
 */
function get_list_query($index) {
	global $config;

	return $config["op5"]["list_query"][$index];
}

/**
 * Get the list query filter from filter name and index
 * 
 * @param string $filterName
 * @param int $queryIndex
 * 
 * @return array
 */
function get_list_query_filter($filterName, $queryIndex, $config_type) {
	return get_list_query($queryIndex)[$config_type][$filterName];
}

/**
 * Parse op5 host to a ansible vars json string
 * 
 * @param array $host
 * @param array $host_vars
 * @param array $columns
 * 
 * @return array
 */
function parse_host_vars($host, $host_vars, $columns) {
	$main = array();

	foreach($host_vars as $key => $var) {
		switch($key) {
			case "ansible_port":
				if(array_key_exists("ansible_port", $host)) {
					$main[$key] = $host['ansible_port'];
				} else if(is_array($host_vars[$key]) && count($host_vars[$key]) > 0) {
					$main[$key] = $host_vars[$key][0];
				} else {
					$main[$key] = 22;
				}
				break;
			default:
				$main[$key] = array_key_exists($var, $columns) ? $host[$columns[$var]] : $var;
				break;
		}
	}

	return $main;
}

/**
 * Create a static inventory list file
 * 
 * @param array $data
 * @param string $filename
 * @param string $group
 * @param boolean $append
 */
function create_static_inventory_file($data, $filename) {
	$myfile = fopen($filename, "w") or die("Unable to open file!");

	$meta = array_key_exists('_meta', $data) ? $data['_meta'] : null;

	foreach($data as $groupName => $groupValues) {
		$add_n = 1;
		if($groupName !== '_meta') {
			fwrite($myfile, "[" . $groupName . "]\n");
			foreach($groupValues["hosts"] as $host) {
				fwrite($myfile, $host);
				foreach($meta["hostvars"][$host] as $hostVarsName => $hostVarsValueName) {
					$s = " " . $hostVarsName . "=" . $hostVarsValueName;
					fwrite($myfile, $s);
				}
				fwrite($myfile, "\n");
			}

			fwrite($myfile, "\n");

			if(array_key_exists("vars", $groupValues) && count($groupValues["vars"]) > 0) {
				fwrite($myfile, "[" . $groupName . ":vars]\n");
				foreach($groupValues["vars"] as $groupVarsName => $groupVarsValueName) {
					$s = $groupVarsName . "=" . $groupVarsValueName . "\n";
					fwrite($myfile, $s);
				}
				fwrite($myfile, "\n");
			}

			if(array_key_exists("children", $groupValues) && count($groupValues["children"]) > 0) {
				fwrite($myfile, "[" . $groupName . ":children]\n");
				foreach($groupValues["children"] as $child) {
					fwrite($myfile, $child . "\n");
				}
				fwrite($myfile, "\n");
			}
		}
	}

	fclose($myfile);
}

/**
 * Output op5 data
 * 
 * @param array $opts
 * 
 * @return string
 */
function get_inventory($opts) {	
	global $config;

	if(!array_key_exists("help", $opts) && (array_key_exists("list", $opts) || array_key_exists("static", $opts))) {
		$data = get_host_list();
		$data = filter_active_hosts($data, "host_filters");

		$data_group = get_host_list_from_group();
		$data_group = filter_active_group_hosts($data_group, "group_filters");

		$host_list_ansible = parse_host_list_to_ansible($data, "host_filters");
		$group_list_ansible = parse_group_list_to_ansible($data_group, "group_filters");

		$data_ansible = array_merge(array_filter($host_list_ansible, function($k) {
			return $k != "_meta";
		}), array_filter($group_list_ansible, function($k) {
			return $k != "_meta";
		}));

		$data_ansible["_meta"] = array_merge($host_list_ansible["_meta"], $group_list_ansible["_meta"]);
				
		if(array_key_exists("list", $opts)) {
			$ret = json_encode($data_ansible);
		} else if(array_key_exists("static", $opts)) {
			create_static_inventory_file(
				$data_ansible, 
				array_key_exists("static_filename", $opts) ? $opts["static_filename"] : "op5_hosts.ansible"
			);
			$ret = "";
		}		
	} elseif(!array_key_exists("help", $opts) && array_key_exists("host", $opts)) {
		$data = get_host($opts["host"]);
		$data = is_host_active($data);

		foreach($data as $key => $value) {
			if(count($data[$key][0]) > 0) {
				$host_vars = parse_host_vars($data[$key][0], 
					$config["op5"]["host_query"][$key]["host_vars"],
					$config["op5"]["host_query"][$key]["columns"]
				);
				break;
			}
		}
		
		$ret = isset($host_vars) ? json_encode($host_vars) : "{}";
	} else {
		$ret = "Usage: get_inventory.php [OPTION]\n";
		$ret .= "op5-ansible-dynamic-inventory opengd@2018\n\n";
		$ret .= "--list\t\t\t\tget json list of op5 hosts\n";
		$ret .= "--host=host\t\t\tget ansible meta variable from op5 host\n";
		$ret .= "--static\t\t\tcreate inventory file from op5 hosts\n";
		$ret .= "--static_filename=FILENAME\tfilename of static inventory\n";
		$ret .= "--config_file=CONFIG_FILE\tfilepath to config file\n";
		$ret .= "--verbose\t\t\tshow verbose data and errors\n";
		$ret .= "--help\t\t\t\tshow this help message\n";
	}
	
	return $ret;
}

$longopts = array(
    "list",
	"host:",
	"static",
	"static_group",
	"static_filename:",
	"static_limit:",
	"config_file:",
	"verbose",
	"help",
);

$opts = getopt("", $longopts);

$verbose = array_key_exists("verbose", $opts);

$config_file_resource = array_key_exists("config_file", $opts) ? fopen($opts["config_file"], "r") : fopen(CONFIG_FILE, "r");

if($config_file_resource) {
	$config_file = fread($config_file_resource, filesize(array_key_exists("config_file", $opts) ? $opts["config_file"] : DEFAULT_CONFIG_FILE));
	fclose($config_file_resource);
	$config = json_decode($config_file, true);
}

echo ($verbose) ? "\n" . get_inventory($opts) . "\n" : get_inventory($opts) ;