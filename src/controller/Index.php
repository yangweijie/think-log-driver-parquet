<?php

namespace think\log\driver\controller;

use think\log\driver\Utils;

class Index
{
    public function index(){
        return view(__DIR__.'/../view/index.html', [
            'logViewerScriptVariables' => [
                'headers' => (object) [],
                'assets_outdated' => false,
                'version' => '111',
                'app_name' => config('app.name'),
                'path' => config('log-viewer.route_path'),
                'back_to_system_url' => config('log-viewer.back_to_system_url'),
                'back_to_system_label' => config('log-viewer.back_to_system_label'),
                'max_log_size_formatted' => Utils::bytesForHumans(131_072),
                'show_support_link' => config('log-viewer.show_support_link', true),

                'supports_hosts' => false,
                'hosts' => [
                    'local' => [
                        'name' => ucfirst(env('APP_ENV', 'local')),
                    ],
                ],
            ],
        ]);
    }
}