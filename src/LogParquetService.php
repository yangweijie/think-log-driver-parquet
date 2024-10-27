<?php
declare (strict_types = 1);

namespace think\log\driver;

use think\Service;

class LogParquetService extends Service
{
    public function register(): void
    {
        $this->checkHotfix();
        $this->bindRoute();
    }

    public function checkHotfix(){
        $app_path = realpath(\Composer\InstalledVersions::getRootPackage()['install_path']);
        $composer_json = $app_path.DIRECTORY_SEPARATOR.'composer.json';
        if(is_file($composer_json)){
            $json = file_get_contents($composer_json);
            $arr = json_decode($json, true);
            if(!isset($arr['extra'])){
                throw new \Exception("no extra section");
            }else{
                if(isset($arr['extra']['include_files'])){
                    $has = false;
                    foreach($arr['extra']['include_files'] as $include_file){
                        if($include_file == 'vendor/yangweijie/think-log-driver-parquet/replace/Path.php'){
                            $has = true;
                        }
                    }
                    if(!$has){
                        throw new \Exception("Path polyfill not be setted correct !");
                    }
                }else{
                    throw new \Exception("no [include files] setting in extra section");
                }
            }
        }else{
            throw new \Exception("Composer.json file does not exist");
        }
        return true;
    }

    private function bindRoute()
    {
        $this->app->route->get('/log-view', '\\think\\log\\driver\\controller\\Index@index');
    }
}


