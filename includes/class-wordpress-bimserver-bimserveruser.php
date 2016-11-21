<?php

namespace WordPressBimserver;


class BimserverUser {
   private $user = false;
   private $bimserverPassword;
   private $isBimserverUser = false;
   private $bimserver;
   private $bimserverUserSettings = false;

   /**
    * @param int $userId
    *
    * @throws \Exception
    */
   public function __construct( $userId = -1 ) {
      if( $userId == -1 && is_user_logged_in() ) {
         $userId = get_current_user_id();
      }

      if( $userId != -1 ) {
         $this->user = get_userdata( $userId );
         $this->bimserverPassword = get_user_meta( $this->user->ID, '_bimserver_password', true );
         if( $this->bimserverPassword != '' ) {
            $this->isBimserverUser = true;
            $options = WordPressBimserver::getOptions();
            $this->bimserver = new BimServerApi( $options['url'] );
            $this->authenticateWithBimServer();
            $settings = get_user_meta( $this->user->ID, '_bimserver_settings', true );
            if( $settings != '' ) {
               $this->bimserverUserSettings = $settings;
            } else {
               $this->bimserverUserSettings = false;
            }
         } else {
            $this->isBimserverUser = false;
         }
      } else {
         throw new \Exception( 'Unknown user id' );
      }
   }

   private function authenticateWithBimServer() {
      $currentToken = get_user_meta( $this->user->ID, '_bimserver_token', true );
      if( $currentToken != '' ) {
         $this->bimserver->setToken( $currentToken );
         try {
            $isLoggedIn = $this->bimserver->apiCall( 'Bimsie1AuthInterface', 'isLoggedIn' );
            if( isset( $isLoggedIn['response'], $isLoggedIn['response']['result'] ) ) {
               $invalidToken = !$isLoggedIn['response']['result'];
            } else {
               $invalidToken = true;
            }
         } catch( \Exception $e ) {
            $invalidToken = true;
         }
      } else {
         $invalidToken = true;
      }
      if( $invalidToken ) {
         $token = $this->bimserver->apiCall( 'Bimsie1AuthInterface', 'login', Array( 'username' => $this->user->user_email, 'password' => $this->bimserverPassword ) );
         if( isset( $token['response'], $token['response']['result'] ) ) {
            $this->bimserver->setToken( $token['response']['result'] );
            update_user_meta( $this->user->ID, '_bimserver_token', $token['response']['result'] );
         } else {
            throw new \Exception( 'Could not authenticate with Bimserver' );
         }
      }
   }

   /**
    * @param string     $interface
    * @param string     $method
    * @param array      $parameters
    *
    * @return array|bool|mixed|object
    */
   public function apiCall( $interface, $method, $parameters = Array() ) {
      if( isset( $this->bimserver ) ) {
         return $this->bimserver->apiCall( $interface, $method, $parameters );
      } else {
         return false;
      }
   }

   /**
    * @return false|\WP_User
    */
   public function getUser() {
      return $this->user;
   }

   /**
    * @param \WP_User $user
    */
   public function setUser( $user ) {
      $this->user = $user;
   }

   /**
    * @return boolean
    */
   public function isBimserverUser() {
      return $this->isBimserverUser;
   }

   /**
    * @return bool|mixed
    */
   public function getBimserverUserSettings() {
      return $this->bimserverUserSettings;
   }

   public function setBimserverUserSettings( $bimserverUserSettings ) {
      $this->bimserverUserSettings = $bimserverUserSettings;
      update_user_meta( $this->user->ID, '_bimserver_settings', $this->bimserverUserSettings );
      $success = false;
      $options = WordPressBimserver::getOptions();
      // Store on Bimserver too
      try {
         $parameters = Array();
         foreach( $this->bimserverUserSettings['parameters'] as $parameter ) {
            $parameters[] = Array(
              '__type' => 'SParameter',
              'name' => $parameter['name'],
              'value' => Array(
                  '__type' => 'SStringType',
                  'value' => $parameter['value']
              )
            );
         }
         $this->apiCall( 'PluginInterface', 'setPluginSettings', Array(
            'poid' => $options['service_id'],
            'settings' => Array(
               '__type' => 'SObjectType',
               'parameters' => $parameters
            )
         ) );
      } catch( \Exception $e ) {
         print( $e->getMessage() );
      }
      return $success;
   }

   /**
    *
    */
   public function retrieveBimserverUserSettings() {
      // Get the first public profile if available
      $options = WordPressBimserver::getOptions();
      try {
         $result = $this->apiCall( 'ServiceInterface', 'getAllLocalProfiles', Array(
             'serviceIdentifier' => $options['service_id']
         ) );
         if( isset( $result['response'], $result['response']['result'] ) ) {
            if( count( $result['response']['result'] ) > 0 ) {
               $profile = $result['response']['result'][0];

            } else {
               $profile = false;
            }
            $this->bimserverUserSettings = Array(
                'profile' => $profile
            );
            // Get configuration options
            $pluginDescriptor = $this->apiCall( 'PluginInterface', 'getInternalServiceById', Array(
               'oid' => $options['service_id']
            ) );
            if( isset( $pluginDescriptor['response'], $pluginDescriptor['response']['result'], $pluginDescriptor['response']['result']['pluginDescriptorId'] ) ) {
               $configurationOptions = $this->apiCall( 'PluginInterface', 'getPluginObjectDefinition', Array(
                   'oid' => $pluginDescriptor['response']['result']['pluginDescriptorId']
               ) );
               if( isset( $configurationOptions['response'], $configurationOptions['response']['result'], $configurationOptions['response']['result']['parameters'] ) ) {
                  $this->bimserverUserSettings['parameters'] = $configurationOptions['response']['result']['parameters'];
               } else {
                  $this->bimserverUserSettings['parameters'] = Array();
               }
            } else {
               $this->bimserverUserSettings['parameters'] = Array();
            }
            update_user_meta( $this->user->ID, '_bimserver_settings', $this->bimserverUserSettings );
            return $this->bimserverUserSettings;
         } else {
            return false;
         }
      } catch( \Exception $e ) {
         var_dump( $e );
         return false;
      }
   }

   public function addProject( $name ) {
      if( $this->isBimserverUser() ) {
         $options = WordPressBimserver::getOptions();
         $sanitizedName = sanitize_title( $name );
         if( $sanitizedName != '' ) {
            try {
               $existingProjects = $this->apiCall( 'Bimsie1ServiceInterface', 'getAllProjects', Array( 'onlyTopLevel' => true, 'onlyActive' => true ) );
               $number = 1;
               $numberText = '';
               $unique = false;
               while( !$unique ) {
                  if( $number > 1 ) {
                     $numberText = '-' . $number;
                  }
                  $unique = true;
                  foreach( $existingProjects['response']['result'] as $project ) {
                     if( $sanitizedName . $numberText == $project['name'] ) {
                        $unique = false;
                        break 1;
                     }
                  }
                  $number ++;
               }
               $poid = $this->apiCall( 'Bimsie1ServiceInterface', 'addProject', Array(
                   'projectName' => $sanitizedName . $numberText,
                   'schema' => $options['project_scheme']
               ) );
               if( isset( $poid['response'], $poid['response']['result'], $poid['response']['result']['oid'] ) ) {
                  $poid = $poid['response']['result']['oid'];
                  // Add the configured service to this project
                  $sService = $this->getSServiceObject( $poid );
                  $this->apiCall( 'ServiceInterface', 'addLocalServiceToProject', Array(
                      'poid' => $poid,
                      'internalServiceOid' => $options['service_id'],
                      'sService' => $sService
                  ) );
               } else {
                  return false;
               }
            } catch( \Exception $e ) {
               var_dump( $e );
               $poid = false;
            }
            return $poid;
         } else {
            return false;
         }
      } else {
         return false;
      }
   }

   public function getSServiceObject( $poid ) {
      $service = $this->getServiceInformation();
      if( $service !== false ) {
         $options = WordPressBimserver::getOptions();
         $sService = Array(
            '__type' => 'SService',
            'name' => $service['name'],
            'providerName' => $service['providerName'],
            'serviceIdentifier' => $options['service_id'],
            'serviceName' => $service['name'],
            'url' => $service['url'],
            'token' => $service['token'],
            'notificationProtocol' => $service['notificationProtocol'],
            'description' => $service['description'],
            'trigger' => $service['trigger'],
            'profileIdentifier' => $options['service_id'],
            'profileName' => $service['name'],
            'profileDescription' => $service['description'],
            'profilePublic' => false,
            'readRevision' => $service['readRevision'],
            'readExtendedDataId' => isset( $service['readExtendedDataId'] ) ? $service['readExtendedDataId'] : -1,
            'writeRevisionId' => $poid,
            'writeExtendedDataId' => isset( $service['writeExtendedDataId'] ) ? $service['writeExtendedDataId'] : -1,
            'modelCheckers' => Array(), // TODO: Array of modelchecker ids?
            /*'internalServiceId' => $options['service_id'],
            'oid' => $options['service_id'],
            'projectId' => $poid,
            'userId' => get_user_meta( $this->user->ID, '_bimserver_uoid', true ),
            'rid' => -1*/
         );
         if( isset( $this->bimserverUserSettings, $this->bimserverUserSettings['profile'] ) && $this->bimserverUserSettings['profile'] !== false ) {
            if( isset( $this->bimserverUserSettings['profile']['profileIdentifier'] ) ) {
               $sService['profileIdentifier'] = $this->bimserverUserSettings['profile']['profileIdentifier'];
            }
            if( isset( $this->bimserverUserSettings['profile']['profileName'] ) ) {
               $sService['profileName'] = $this->bimserverUserSettings['profile']['profileName'];
            }
            if( isset( $this->bimserverUserSettings['profile']['profileDescription'] ) ) {
               $sService['profileDescription'] = $this->bimserverUserSettings['profile']['profileDescription'];
            }
            if( isset( $this->bimserverUserSettings['profile']['profilePublic'] ) ) {
               $sService['profilePublic'] = $this->bimserverUserSettings['profile']['profilePublic'];
            }
         }
         return $sService;
      } else {
         return false;
      }
   }

   public function getServiceInformation() {
      $service = get_option( '_wordpress_bimserver_service' );
      if( $service == '' ) {
         $options = WordPressBimserver::getOptions();
         $services = $this->apiCall( 'ServiceInterface', 'getAllLocalServiceDescriptors' );
         $service = false;
         if( isset( $services['response'], $services['response']['result'] ) ) {
            foreach( $services['response']['result'] as $checkService ) {
               if( $checkService['identifier'] == $options['service_id'] ) {
                  $service = $checkService;
                  if( isset( $service['writeExtendedData'] ) && $service['writeExtendedData'] != '' ) {
                     // get the write extended data information
                     try {
                        $result = $this->apiCall( 'Bimsie1ServiceInterface', 'getExtendedDataSchemaByNamespace', Array(
                            'namespace' => $service['writeExtendedData']
                        ) );
                        if( isset( $result['response'], $result['response']['result'], $result['response']['result']['oid'] ) ) {
                           $service['writeExtendedDataId'] = $result['response']['result']['oid'];
                        }
                     } catch( \Exception $e ) {
                        // Could not find an extended data scheme, so we leave it empty
                     }
                  }
                  break;
               }
            }
         }
         if( $service !== false ) {
            update_option( '_wordpress_bimserver_service', $service );
         }
      }
      return $service;
   }

   public function getProgress( $topicId ) {
      try {
         $progress = $this->apiCall( 'Bimsie1NotificationRegistryInterface', 'getProgress', Array(
             'topicId' => $topicId
         ) );
      } catch( \Exception $e ) {
         $progress = null;
      }
      if( isset( $progress, $progress['response'], $progress['response']['result'], $progress['response']['result']['progress'] ) ) {
         if( $progress['response']['result']['progress'] > 0 ) {
            return $progress['response']['result']['progress'] * 0.01;
         } else {
            return 0;
         }
      } else {
         return 1;
      }
   }

   public function hasConfiguredService() {
      return $this->bimserverUserSettings !== false;
   }

   public function downloadData( $poid ) {
      try {
         // get the correct revision
         $revisions = $this->apiCall( 'Bimsie1ServiceInterface', 'getAllRevisionsOfProject', Array(
            'poid' => $poid
         ) );

         if( isset( $revisions['response'], $revisions['response']['result'], $revisions['response']['result'][0] ) ) {
            $revisionId = $revisions['response']['result'][0]['oid'];
         } else {
            throw new \Exception( __( 'Could not find any revisions for this project, something must have gone wrong uploading', 'wordpress-bimserver' ) );
         }

         // Make sure if we need to download the model or extended data
         $service = $this->getServiceInformation();
         $options = WordPressBimserver::getOptions();
         if( isset( $service['writeExtendedDataId'] ) && $service['writeExtendedDataId'] != '' ) {
            // There is extended data, so we download that
            $extendedData = $this->apiCall( 'Bimsie1ServiceInterface', 'getAllExtendedDataOfRevision', Array(
                'roid' => $revisionId
            ) );
            if( isset( $extendedData['response'], $extendedData['response']['result'], $extendedData['response']['result'][0] ) ) {
               $extendedDataId = $extendedData['response']['result'][0]['oid'];
               $data = file_get_contents( $options['url'] . '/download?token=' . get_user_meta( $this->user->ID, '_bimserver_token', true ) . '&action=extendeddata&edid=' . $extendedDataId );
               header( 'Content-Type: ' . $options['mime_type'] );
               header( 'Content-Disposition: attachment; filename="' . $service['writeExtendedData'] . '"' );
               print( $data );
            } else {
               throw new \Exception( __( 'Could not find any extended data for this project, please contact an administrator', 'wordpress-bimserver' ) );
            }
         } else {
            // There is no extended data, just do a zip revision download

            // Get default serializer
            $defaultSerializer = $this->apiCall( 'PluginInterface', 'getDefaultSerializer' );

            if( isset( $defaultSerializer['response'], $defaultSerializer['response']['result'], $defaultSerializer['response']['result']['oid'] ) ) {
               $download = $this->apiCall( 'Bimsie1ServiceInterface', 'download', Array(
                   'allowCheckouts' => true,
                   'downloadType' => 'single',
                   'poid' => $poid,
                   'roid' => $revisionId,
                   'serializerOid' => $defaultSerializer['response']['result']['oid'],
                   'showOwn' => true,
                   'sync' => true,
                   'zip' => true
               ) );
               if( isset( $download['response'], $download['response']['result'] ) ) {
                  $topicId = $download['response']['result'];
                  /*$endPointId = -1; // TODO: get this from where?
                  $this->apiCall( 'Bimsie1NotificationRegistryInterface', 'registerProgressHandler', Array(
                     'topicId' => $topicId,
                     'endPointId' => $endPointId
                  ) );*/
                  $tries = 5;
                  $done = false;
                  while( !$done && $tries >= 0 ) {
                     $tries --;
                     $progress = $this->apiCall( 'Bimsie1NotificationRegistryInterface', 'getProgress', Array(
                        'topicId' => $topicId
                     ) );
                     if( isset( $progress['response'], $progress['response']['result'], $progress['response']['result']['progress'] ) && $progress['response']['result']['progress'] >= 1 ) {
                        $done = true;
                     }
                     sleep( 1 );
                  }
                  if( $done ) {
                     $data = file_get_contents( $options['url'] . '/download?token=' . get_user_meta( $this->user->ID, '_bimserver_token', true ) .
                         '&longActionId=' . $topicId . '&zip=on&serializerOid=' . $defaultSerializer['response']['result']['oid'] . '&topicId=' . $topicId );
                     header( 'Content-Type: ' . $options['mime_type'] );
                     header( 'Content-Disposition: attachment; filename="' . $service['writeExtendedData'] . '"' );
                     print( $data );
                  } else {
                     throw new \Exception( __( 'Download took too long to become available', 'wordpress-bimserver' ) );
                  }
               }
            }
         }
      } catch( \Exception $e ) {
         _e( 'There was a problem downloading this data: ', 'wordpress-bimserver' );
         print( '<br />' . $e->getMessage() );
      }
   }
}