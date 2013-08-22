<?php
class ControllerExtensionInstaller extends Controller {
	private $error = array();
   
  	public function index() {
		$this->language->load('extension/installer');
	
    	$this->document->setTitle($this->language->get('heading_title'));
		
     	$this->data['heading_title'] = $this->language->get('heading_title');

		$this->data['entry_upload'] = $this->language->get('entry_upload');
		$this->data['entry_overwrite'] = $this->language->get('entry_overwrite');
		$this->data['entry_progress'] = $this->language->get('entry_progress');

		$this->data['help_upload'] = $this->language->get('help_upload');

		$this->data['button_upload'] = $this->language->get('button_upload');
		$this->data['button_clear'] = $this->language->get('button_clear');
		$this->data['button_continue'] = $this->language->get('button_continue');
		
  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL')
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/installer', 'token=' . $this->session->data['token'], 'SSL')
   		);
		
		$this->data['token'] = $this->session->data['token'];
		
		$directories = glob(DIR_DOWNLOAD . 'temp-*', GLOB_ONLYDIR);
		
		if ($directories) {
			$this->data['error_warning'] = $this->language->get('error_temporary');
		} else {
			$this->data['error_warning'] = '';
		}
		
		$this->template = 'extension/installer.tpl';
		$this->children = array(
			'common/header',
			'common/footer'
		);
				
		$this->response->setOutput($this->render());	
  	}
	
	public function upload() {		
		$this->language->load('extension/installer');
		
		$json = array();
		
		if (!$this->user->hasPermission('modify', 'extension/installer')) {
      		$json['error'] = $this->language->get('error_permission');
    	}
		
		if (!empty($this->request->files['file']['name'])) {
			if (strrchr($this->request->files['file']['name'], '.') != '.zip' && strrchr($this->request->files['file']['name'], '.') != '.xml') {
				$json['error'] = $this->language->get('error_filetype');
       		}
					
			if ($this->request->files['file']['error'] != UPLOAD_ERR_OK) {
				$json['error'] = $this->language->get('error_upload_' . $this->request->files['file']['error']);
			}
		} else {
			$json['error'] = $this->language->get('error_upload');
		}
			
		if (!$json) {
			// If no temp directory exists create it
			$path = 'temp-' . md5(mt_rand());
			
			if (!is_dir(DIR_DOWNLOAD . $path)) {
				mkdir(DIR_DOWNLOAD . $path, 0777);
			}
			
			// Set the steps required for installation
			$json['step'] = array();
			$json['overwrite'] = array();
			
			if (strrchr($this->request->files['file']['name'], '.') == '.xml') {
				$file = DIR_DOWNLOAD . $path . '/install.xml';
				
				// If xml file copy it to the temporary directory
				move_uploaded_file($this->request->files['file']['tmp_name'], $file);
				
				if (file_exists($file)) {	
					$json['step'][] = array(
						'text' => $this->language->get('text_xml'),
						'url'  => str_replace('&amp;', '&', $this->url->link('extension/installer/xml', 'token=' . $this->session->data['token'], 'SSL')),
						'path' => $path
					);
					
					// Clear temporary files
					$json['step'][] = array(
						'text' => $this->language->get('text_remove'),
						'url'  => str_replace('&amp;', '&', $this->url->link('extension/installer/remove', 'token=' . $this->session->data['token'], 'SSL')),
						'path' => $path
					);
				} else {
					$json['error'] = $this->language->get('error_file');
				}
			}
			
			// If zip file copy it to the temp directory
			if (strrchr($this->request->files['file']['name'], '.') == '.zip') {
				$file = DIR_DOWNLOAD . $path . '/upload.zip';
				
				move_uploaded_file($this->request->files['file']['tmp_name'], $file);
				
				if (file_exists($file)) {					
					$zip = zip_open($file);
					
					if ($zip) {
						// Zip
						$json['step'][] = array(
							'text' => $this->language->get('text_unzip'),
							'url'  => str_replace('&amp;', '&', $this->url->link('extension/installer/unzip', 'token=' . $this->session->data['token'], 'SSL')),
							'path' => $path
						);
							
						// FTP
						$json['step'][] = array(
							'text' => $this->language->get('text_ftp'),
							'url'  => str_replace('&amp;', '&', $this->url->link('extension/installer/ftp', 'token=' . $this->session->data['token'], 'SSL')),
							'path' => $path
						);
																				
						while ($entry = zip_read($zip)) {
							$zip_name = zip_entry_name($entry);
							
							// SQL
							if (substr($zip_name, 0, 11) == 'install.sql') {
								$json['step'][] = array(
									'text' => $this->language->get('text_sql'),
									'url'  => str_replace('&amp;', '&', $this->url->link('extension/installer/sql', 'token=' . $this->session->data['token'], 'SSL')),
									'path' => $path
								);
							}		
							
							// XML					
							if (substr($zip_name, 0, 11) == 'install.xml') {
								$json['step'][] = array(
									'text' => $this->language->get('text_xml'),
									'url'  => str_replace('&amp;', '&', $this->url->link('extension/installer/xml', 'token=' . $this->session->data['token'], 'SSL')),
									'path' => $path
								);								
							}

							// PHP
							if (substr($zip_name, 0, 11) == 'install.php') {
								$json['step'][] = array(
									'text' => $this->language->get('text_php'),
									'url'  => str_replace('&amp;', '&', $this->url->link('extension/installer/php', 'token=' . $this->session->data['token'], 'SSL')),
									'path' => $path
								);
							}
														
							// Compare admin files
							$file = DIR_APPLICATION . substr($zip_name, 13);
							
							if (is_file($file) && substr($zip_name, 0, 13) == 'upload/admin/') {
								$json['overwrite'][] = substr($zip_name, 7);
							}
							
							// Compare catalog files
							$file = DIR_CATALOG . substr($zip_name, 7);
							
							if (is_file($file) && substr($zip_name, 0, 15) == 'upload/catalog/') {
								$json['overwrite'][] = substr($zip_name, 7);
							}
							
							// Compare image files
							$file = DIR_IMAGE . substr($zip_name, 13);
							
							if (is_file($file) && substr($zip_name, 0, 13) == 'upload/image/') {
								$json['overwrite'][] = substr($zip_name, 7);
							}
							
							// Compare system files
							$file = DIR_SYSTEM . substr($zip_name, 14);											
							
							if (is_file($file) && substr($zip_name, 0, 14) == 'upload/system/') {
								$json['overwrite'][] = substr($zip_name, 7);
							}
						}
			
						// Clear temporary files
						$json['step'][] = array(
							'text' => $this->language->get('text_remove'),
							'url'  => str_replace('&amp;', '&', $this->url->link('extension/installer/remove', 'token=' . $this->session->data['token'], 'SSL')),
							'path' => $path
						);	
																
						zip_close($zip);
					} else {
						$json['error'] = $this->language->get('error_unzip');
					}			
				} else {
					$json['error'] = $this->language->get('error_file');
				}			
			}
		}
					
		$this->response->setOutput(json_encode($json));
	}
	
	public function unzip() {
		$this->language->load('extension/installer');
		
		$json = array();
		
		if (!$this->user->hasPermission('modify', 'extension/installer')) {
      		$json['error'] = $this->language->get('error_permission');
    	}

		// Sanitize the filename	
		$file = DIR_DOWNLOAD . str_replace(array('../', '..\\', '..'), '', $this->request->post['path']) . '/upload.zip';

		if (!file_exists($file)) {
			$json['error'] = $this->language->get('error_file');
		}

		if (!$json) {
			// Unzip the files
			$zip = new ZipArchive();
			
			if ($zip->open($file)) {
				$zip->extractTo(DIR_DOWNLOAD . str_replace(array('../', '..\\', '..'), '', $this->request->post['path']));
				$zip->close();				
			} else {
				$json['error'] = $this->language->get('error_unzip');
			}
			
			// Remove Zip
			unlink($file);		
		}
		
		$this->response->setOutput(json_encode($json));
	}
		
	public function ftp() {
		$this->language->load('extension/installer');
		
		$json = array();
		
		if (!$this->user->hasPermission('modify', 'extension/installer')) {
      		$json['error'] = $this->language->get('error_permission');
    	}
		
		$directory = DIR_DOWNLOAD . str_replace(array('../', '..\\', '..'), '', $this->request->post['path']) . '/upload/';
		
		if (!is_dir($directory)) {
			$json['error'] = $this->language->get('error_directory');
		}
		
		if (!$json) {
			// Get a list of files ready to upload
			$files = array();
			
			$path = array($directory . '*');
			
			while(count($path) != 0) {
				$next = array_shift($path);
		
				foreach(glob($next) as $file) {
					if (is_dir($file)) {
						$path[] = $file . '/*';
					}
						
					$files[] = $file;
				}
			}
			
			// Connect to the site via FTP
			$connection = ftp_connect($this->config->get('config_ftp_host'), $this->config->get('config_ftp_port'));
	
			if ($connection) {
				$login = ftp_login($connection, $this->config->get('config_ftp_username'), $this->config->get('config_ftp_password'));
				
				if ($login) {
					if ($this->config->get('config_ftp_root')) {
						$root = ftp_chdir($connection, $this->config->get('config_ftp_root'));
					} else {
						$root = ftp_chdir($connection, '/');
					}
					
					if ($root) {
						foreach ($files as $file) {
							// Upload everything in the upload directory
							$destination = substr($file, strlen($directory));
							
							if (is_dir($file)) {
								$list = ftp_nlist($connection, substr($destination, 0, strrpos($destination, '/')));
								
								if (!in_array($destination, $list)) {
									if (!ftp_mkdir($connection, $destination)) {
										$json['error'] = sprintf($this->language->get('error_ftp_directory'), $destination);
									}
								}
							}	

							if (is_file($file)) {
								if (!ftp_put($connection, $destination, $file, FTP_BINARY)) {
									$json['error'] = sprintf($this->language->get('error_ftp_file'), $file);
								}
							}
						}
					} else {
						$json['error'] = sprintf($this->language->get('error_ftp_root'), $root);
					}
				} else {
					$json['error'] = sprintf($this->language->get('error_ftp_login'), $this->config->get('config_ftp_username'));
				}
				
				ftp_close($connection);	
			} else {
				$json['error'] = sprintf($this->language->get('error_ftp_connection'), $this->config->get('config_ftp_host'), $this->config->get('config_ftp_port'));
			}
		}
		
		$this->response->setOutput(json_encode($json));		
	}
	
	public function sql() {
		$this->language->load('extension/installer');
		
		$json = array();
		
		if (!$this->user->hasPermission('modify', 'extension/installer')) {
      		$json['error'] = $this->language->get('error_permission');
    	}

		$file = DIR_DOWNLOAD . str_replace(array('../', '..\\', '..'), '', $this->request->post['path']) . '/install.sql';

		if (!file_exists($file)) {
			$json['error'] = $this->language->get('error_file');
		}
		
		if (!$json) {
			$lines = file($file);
			
			if ($lines) {
				try {	
					$sql = '';
					
					foreach($lines as $line) {
						if ($line && (substr($line, 0, 2) != '--') && (substr($line, 0, 1) != '#')) {
							$sql .= $line;
		  
							if (preg_match('/;\s*$/', $line)) {
								$sql = str_replace(" `oc_", " `" . DB_PREFIX, $sql);
								
								$this->db->query($sql);
			
								$sql = '';
							}
						}
					}
				} catch(Exception $exception) {
					$json['error'] = sprintf($this->language->get('error_exception'), $exception->getCode(), $exception->getMessage(), $exception->getFile(), $exception->getLine());
				}
			}
		}
	
		$this->response->setOutput(json_encode($json));							
	}
	
	public function xml() {
		$this->language->load('extension/installer');
		
		$json = array();
		
		if (!$this->user->hasPermission('modify', 'extension/installer')) {
      		$json['error'] = $this->language->get('error_permission');
    	}
		
		$file = DIR_DOWNLOAD . str_replace(array('../', '..\\', '..'), '', $this->request->post['path']) . '/install.xml';

		if (!file_exists($file)) {
			$json['error'] = $this->language->get('error_file');
		}
					
		if (!$json) {	
			$this->load->model('setting/modification');
			
			// If xml file just put it straight into the DB
			$xml = file_get_contents($file);
			
			if ($xml) {
				try {
					$dom = new DOMDocument('1.0', 'UTF-8');
					$dom->loadXml($xml);

					$data = array(
						'name'       => $dom->getElementsByTagName('name')->item(0)->nodeValue,
						'version'    => $dom->getElementsByTagName('version')->item(0)->nodeValue,
						'author'     => $dom->getElementsByTagName('author')->item(0)->nodeValue,
						'code'       => $file,
						'status'     => 1,
						'sort_order' => 0
					);
				
					$this->model_setting_modification->addModification($data);
				} catch(Exception $exception) {
					$json['error'] = sprintf($this->language->get('error_exception'), $exception->getCode(), $exception->getMessage(), $exception->getFile(), $exception->getLine());
				}
			}
		}
		
		$this->response->setOutput(json_encode($json));
	}
	
	public function php() {
		$this->language->load('extension/installer');
		
		$json = array();
		
		if (!$this->user->hasPermission('modify', 'extension/installer')) {
      		$json['error'] = $this->language->get('error_permission');
    	}
			
		$file = DIR_DOWNLOAD . str_replace(array('../', '..\\', '..'), '', $this->request->post['path']) . '/install.php';

		if (!file_exists($file)) {
			$json['error'] = $this->language->get('error_file');
		}
		
		if (!$json) {
			try {
				include($file);
			} catch(Exception $exception) {
				$json['error'] = sprintf($this->language->get('error_exception'), $exception->getCode(), $exception->getMessage(), $exception->getFile(), $exception->getLine());
			}
		}
			
		$this->response->setOutput(json_encode($json));
	}
		
  	public function remove() {
		$this->language->load('extension/installer');
		
		$json = array();
		
		if (!$this->user->hasPermission('modify', 'extension/installer')) {
      		$json['error'] = $this->language->get('error_permission');
    	}
		
		$directory = DIR_DOWNLOAD . str_replace(array('../', '..\\', '..'), '', $this->request->post['path']);

		if (!is_dir($directory)) {
			$json['error'] = $this->language->get('error_directory');
		}

		if (!$json) {
			// Get a list of files ready to upload
			$files = array();
			
			$path = array($directory . '*');
			
			while(count($path) != 0) {
				$next = array_shift($path);
		
				foreach(glob($next) as $file) {
					if (is_dir($file)) {
						$path[] = $file . '/*';
					}
					
					$files[] = $file;
				}
			}
						
			sort($files);
			
			rsort($files);
						
			foreach ($files as $file) {
				if (is_file($file)) {
					unlink($file);
				} elseif (is_dir($file)) {
					rmdir($file);	
				}
			}
				
			if (file_exists($directory)) {
				rmdir($directory);
			}
			
			$json['success'] = $this->language->get('text_success');
		}
		
		$this->response->setOutput(json_encode($json));
  	}
		
  	public function clear() {
		$this->language->load('extension/installer');
		
		$json = array();
		
		if (!$this->user->hasPermission('modify', 'extension/installer')) {
      		$json['error'] = $this->language->get('error_permission');
    	}
		
		if (!$json) {
			$directories = glob(DIR_DOWNLOAD . 'temp-*', GLOB_ONLYDIR);
			
			foreach($directories as $directory) {
				// Get a list of files ready to upload
				$files = array();
				
				$path = array($directory . '*');
				
				while(count($path) != 0) {
					$next = array_shift($path);
			
					foreach(glob($next) as $file) {
						if (is_dir($file)) {
							$path[] = $file . '/*';
						}
						
						$files[] = $file;
					}
				}
							
				sort($files);
				
				rsort($files);
							
				foreach ($files as $file) {
					if (is_file($file)) {
						unlink($file);
					} elseif (is_dir($file)) {
						rmdir($file);	
					}
				}
					
				if (file_exists($directory)) {
					rmdir($directory);
				}
			}
			
			$json['success'] = $this->language->get('text_clear');
		}
		
		$this->response->setOutput(json_encode($json));
  	}		
}
?>