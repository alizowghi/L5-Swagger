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

        if ($group) {
            $appDir = config('l5-swagger.doc_groups.'.$group.'.paths.annotations', $appDir);
            $docDir = config('l5-swagger.doc_groups.'.$group.'.paths.docs', $docDir);
            $constants = config('l5-swagger.doc_groups.'.$group.'.constants', $constants);
            $excludeDirs = config('l5-swagger.doc_groups.'.$group.'.paths.excludes', $excludeDirs);
            $docsJson = config('l5-swagger.doc_groups.'.$group.'.paths.docs_json', $docsJson);
            $securityConfig = config('l5-swagger.doc_groups.'.$group.'.security', $securityConfig);
        }

        if (! File::exists($docDir) || is_writable($docDir)) {
            // delete all existing documentation
            if (File::exists($docDir)) {
                File::deleteDirectory($docDir);
            }

            self::defineConstants($constants);

            File::makeDirectory($docDir);
            $swagger = \Swagger\scan($appDir, ['exclude' => $excludeDirs]);

            if (config('l5-swagger.paths.base') !== null) {
                $swagger->basePath = config('l5-swagger.paths.base');
            }

            $filename = $docDir.'/'.$docsJson;
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
