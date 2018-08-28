<?php

namespace L5Swagger;

use File;
use Config;
use L5Swagger\Exceptions\L5SwaggerException;

class Generator
{
    public static function generateDocs($group = '')
    {
        if (!$group && config('l5-swagger.api.separated_doc')) {
            foreach (config('l5-swagger.doc_groups', []) as $group => $groupConfig) {
                self::generateGroupDocs($group);
            }
        } else if ($group) {
            if (!config('l5-swagger.doc_groups.'.$group)) {
                throw new L5SwaggerException('Unknown documentation group: '.$group);
            }

            self::generateGroupDocs($group);
        } else {
            self::generateGroupDocs();
        }
    }

    protected static function generateGroupDocs($group = '')
    {
        $appDir = config('l5-swagger.paths.annotations');
        $docDir = config('l5-swagger.paths.docs');
        $constants = config('l5-swagger.constants') ?: [];
        $excludeDirs = config('l5-swagger.paths.excludes');
        $docsJson = config('l5-swagger.paths.docs_json', 'api-docs.json');
        $securityConfig = config('l5-swagger.security', []);
        $basePath = config('l5-swagger.paths.base');

        if ($group) {
            $appDir = config('l5-swagger.doc_groups.'.$group.'.paths.annotations', $appDir);
            $docDir = config('l5-swagger.doc_groups.'.$group.'.paths.docs', $docDir);
            $constants = config('l5-swagger.doc_groups.'.$group.'.constants', $constants);
            $excludeDirs = config('l5-swagger.doc_groups.'.$group.'.paths.excludes', $excludeDirs);
            $docsJson = config('l5-swagger.doc_groups.'.$group.'.paths.docs_json', $group.'-'.$docsJson);
            $securityConfig = config('l5-swagger.doc_groups.'.$group.'.security', $securityConfig);
            $basePath = config('l5-swagger.doc_groups.'.$group.'.paths.base', $basePath);
        }

        if (! File::exists($docDir) || is_writable($docDir)) {
            $filename = $docDir.'/'.$docsJson;

            if (! File::exists($docDir)) {
                File::makeDirectory($docDir);
            }

            // delete existing documentation
            if (File::exists($filename)) {
                File::deleteDirectory($filename);
            }

            self::defineConstants($constants);
            if (version_compare(config('swagger-lume.swagger_version'), '3.0', '>=')) {
                $swagger = \OpenApi\scan($appDir, ['exclude' => $excludeDirs]);
            } else {
                $swagger = \Swagger\scan($appDir, ['exclude' => $excludeDirs]);
            }

            if (config('l5-swagger.paths.base') !== null) {
                $swagger->basePath = $basePath;
            }

            $swagger->saveAs($filename);

            if (is_array($securityConfig) && ! empty($securityConfig)) {
                $documentation = collect(
                    json_decode(file_get_contents($filename))
                );

                $securityDefinitions = $documentation->has('securityDefinitions') ? collect($documentation->get('securityDefinitions')) : collect();

                foreach ($securityConfig as $key => $cfg) {
                    $securityDefinitions->offsetSet($key, self::arrayToObject($cfg));
                }

                $documentation->offsetSet('securityDefinitions', $securityDefinitions);

                file_put_contents($filename, $documentation->toJson());
            }
        }
    }

    protected static function defineConstants(array $constants)
    {
        if (! empty($constants)) {
            foreach ($constants as $key => $value) {
                defined($key) || define($key, $value);
            }
        }
    }

    public static function arrayToObject($array)
    {
        return json_decode(json_encode($array));
    }
}
