# op5-ansible-dynamic-inventory

Script to create a Ansible dynamic inventory from OP5.

## Getting Started

### Prerequisites

To run this script you will need PHP and the PHP cURL module.

```
# Install php curl module for PHP 7.2 on Ubuntu
sudo apt-get install php7.2-curl
```

### Installing

Clone to git rep to get the script and example config file.

```
git clone https://github.com/opengd/op5-ansible-dynamic-inventory.git
```
This will retriev the git repo and you can find the php 

## Usage

op5-ansible-dynamic-inventory script can be config by using a config json file or you can change the config inside the php script file. Using a seperate config file (default: config.json) is recommended.

This is the example config.json, you can find it as config.json.example in the op5-ansible-dynamic-inventory folder. Copy this file and rename it to config.json, and edit the copy to create your own config file.

``` javascript
{
    "op5":{
        "list_query":[
            {
                "userpwd": "api$Default:api",
                "host": "https:\/\/YOUR.OP5.URL",
                "api": "\/api\/filter\/query?format=json&query=",
                "filters": {
                    "demo": {
                        "filter": "[hosts]name~~\"demo*\"",
                        "columns": { 
                            "NAME": "name",
                            "ADDRESS": "address"
                        },
                        "host_vars":{
                            "ansible_port": 22,
                            "ansible_host": "ADDRESS" 
                        },
                        "limit": null,
                        "offset": null,
                        "group_vars": {
                            "var1": true
                        },
                        "children": [],
                        "check_ansible_port" => true
                    }
                }
            }
        ],
        "host_query":[
            {
                "userpwd":"api$Default:aipi",
                "host":"https:\/\/YOUR.OP5.URL",
                "api":"\/api\/filter\/query?format=json&query=",
                "filter":"[hosts]name= {host}",
                "columns": { 
                    "NAME": "name",
                    "ADDRESS": "address"
                },
                "host_vars":{
                    "ansible_port":[22,22022],
                    "ansible_host":"ADDRESS"
                },
                "check_ansible_port" => true
            },
        ]
    }
}
```

Ansible 

## Built With

* [PHP](http://http://php.net) - PHP is a popular general-purpose scripting language that is especially suited to web development.
* [OP5](https://www.op5.com/) - OP5 Monitor
* [Ansible](https://www.ansible.com/) - Ansible automation

## Contributing

Please read [CONTRIBUTING.md](https://gist.github.com/PurpleBooth/b24679402957c63ec426) for details on our code of conduct, and the process for submitting pull requests to us.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details
