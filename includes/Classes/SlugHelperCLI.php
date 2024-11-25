<?php

namespace PluginClassName\Classes;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use WP_CLI;
use FilesystemIterator;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (defined('WP_CLI') && WP_CLI) {
    /**
     * WP-CLI command to replace strings across files in a directory.
     *
     * @package PluginClassName\Classes
     */
    class ReplacePluginSlugCommand {
        /**
         * Allowed file extensions for safety
         *
         * @var array
         */
        private $allowed_extensions = ['php', 'txt', 'js', 'css', 'json', 'md'];
        
        /**
         * Directories to ignore
         *
         * @var array
         */
        private $ignored_directories = [
            'node_modules',
            'vendor',
            '.git',
            'build',
            'dist',
            '.svn',
            'bower_components',
            'tmp',
            'cache'
        ];
        
        /**
         * Execute the replacement command.
         *
         * ## OPTIONS
         *
         * <search>
         * : The string to search for.
         *
         * <replace>
         * : The string to replace with.
         *
         * [--directory=<directory>]
         * : The directory to scan. Default is the current plugin directory.
         *
         * [--dry-run]
         * : Show what would be replaced without making changes.
         *
         * ## EXAMPLES
         *
         *     wp replace-slug old-slug new-slug
         *     wp replace-slug old-slug new-slug --directory=./wp-content/plugins/my-plugin
         *     wp replace-slug old-slug new-slug --dry-run
         *
         * @param array $args       The positional arguments.
         * @param array $assoc_args The associative arguments.
         */
        public function __invoke($args, $assoc_args) {
            if (count($args) !== 2) {
                WP_CLI::error('Please provide both search and replace strings.');
            }
            
            list($search, $replace) = $args;
            
            if (empty($search) || empty($replace)) {
                WP_CLI::error('Search and replace strings cannot be empty.');
            }
            
            // Determine the directory to scan
            $default_directory = plugin_dir_path(dirname(__FILE__, 2));
            $directory = isset($assoc_args['directory']) ? rtrim($assoc_args['directory'], '/') : $default_directory;
            
            if (!is_dir($directory)) {
                WP_CLI::error("The directory '{$directory}' does not exist.");
            }
            
            if (!is_readable($directory)) {
                WP_CLI::error("The directory '{$directory}' is not readable.");
            }
            
            $is_dry_run = isset($assoc_args['dry-run']);
            
            try {
                WP_CLI::log("Scanning directory: {$directory}");
                WP_CLI::log("Ignored directories: " . implode(', ', $this->ignored_directories));
                
                $files = $this->get_files($directory);
                
                if (empty($files)) {
                    WP_CLI::warning("No valid files found in the directory '{$directory}'.");
                    return;
                }
                
                WP_CLI::log("Found " . count($files) . " files to process.");
                $this->process_files($files, $search, $replace, $is_dry_run);
                
            } catch (\Exception $e) {
                WP_CLI::error($e->getMessage());
            }
        }
        
        /**
         * Process the files for replacement.
         *
         * @param array  $files     List of files to process.
         * @param string $search    String to search for.
         * @param string $replace   String to replace with.
         * @param bool   $is_dry_run Whether this is a dry run.
         */
        private function process_files($files, $search, $replace, $is_dry_run) {
            $count = 0;
            $modified_files = [];
            $skipped_files = [];
            
            foreach ($files as $file) {
                if (!is_readable($file)) {
                    $skipped_files[] = ["file" => $file, "reason" => "not readable"];
                    continue;
                }
                
                $contents = file_get_contents($file);
                if ($contents === false) {
                    $skipped_files[] = ["file" => $file, "reason" => "could not read contents"];
                    continue;
                }
                
                $updated_contents = str_replace($search, $replace, $contents);
                
                if ($contents !== $updated_contents) {
                    $modified_files[] = $file;
                    
                    if (!$is_dry_run) {
                        if (!is_writable($file)) {
                            $skipped_files[] = ["file" => $file, "reason" => "not writable"];
                            continue;
                        }
                        
                        $result = file_put_contents($file, $updated_contents);
                        if ($result === false) {
                            $skipped_files[] = ["file" => $file, "reason" => "failed to write"];
                            continue;
                        }
                        $count++;
                        WP_CLI::log("Replaced in file: {$file}");
                    }
                }
            }
            
            // Show results
            if ($is_dry_run) {
                if (!empty($modified_files)) {
                    WP_CLI::log("\nFiles that would be modified:");
                    foreach ($modified_files as $file) {
                        WP_CLI::log("- {$file}");
                    }
                }
                WP_CLI::success("Dry run completed. Found " . count($modified_files) . " file(s) that would be modified.");
            } else {
                WP_CLI::success("String replaced in {$count} file(s).");
            }
            
            // Show skipped files if any
            if (!empty($skipped_files)) {
                WP_CLI::log("\nSkipped files:");
                foreach ($skipped_files as $skip) {
                    WP_CLI::log("- {$skip['file']} (Reason: {$skip['reason']})");
                }
            }
        }
        
        /**
         * Recursively get all files in a directory.
         *
         * @param string $directory The directory to scan.
         * @return array The list of files.
         */
        private function get_files($directory) {
            $files = [];
            
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(
                        $directory,
                        RecursiveDirectoryIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
                    )
                );
                
                foreach ($iterator as $file) {
                    // Skip if parent directory should be ignored
                    foreach ($this->ignored_directories as $ignored) {
                        if (strpos($file->getPathname(), DIRECTORY_SEPARATOR . $ignored . DIRECTORY_SEPARATOR) !== false) {
                            continue 2;
                        }
                    }
                    
                    // Only include files with allowed extensions
                    if ($file->isFile() &&
                        in_array($file->getExtension(), $this->allowed_extensions, true)) {
                        $files[] = $file->getPathname();
                    }
                }
            } catch (\UnexpectedValueException $e) {
                WP_CLI::warning("Permission denied or invalid directory structure in: {$directory}");
            }
            
            return $files;
        }
    }
    
    WP_CLI::add_command('replace-slug', ReplacePluginSlugCommand::class);
}
