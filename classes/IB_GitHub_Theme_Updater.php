<?php

class IB_GitHub_Theme_Updater
{
    /** @var string Filename */
    private $filename;

    /** @var string Basename */
    private $basename;

    /** @var string Theme information  */
    private $theme;

    /** @var array Theme remote information */
    private $repository_info;

    public function __construct($filename)
    {
        $this->filename = $filename;

        add_action('admin_init', [$this, 'set_theme_properties']);

        add_filter('pre_set_site_transient_update_themes', [$this, 'pre_check_update_custom']);
        add_filter('themes_api', [$this, 'get_theme_info'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'upgrader_source_selection'], 10, 3);
    }

    public function get_repository_info()
    {
        if (!isset($this->repository_info)) {
            list($username, $repository) = explode(
                '/',
                trim(parse_url($this->theme->get('ThemeURI'), PHP_URL_PATH), '/')
            );

            $request = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $username, $repository);
            $response = json_decode(wp_remote_retrieve_body(wp_remote_get($request)), true);

            $this->repository_info = $response;
        }
    }

    public function set_theme_properties()
    {
        $this->theme = wp_get_theme();
        $this->basename = $this->theme->get_stylesheet();
    }

    public function pre_check_update_custom($transient)
    {
        if (property_exists($transient, 'checked')) {
            if ($checked = $transient->checked) {
                $this->get_repository_info();

                $out_of_date = version_compare($this->repository_info['tag_name'], $checked[$this->basename], 'gt');

                if ($out_of_date) {
                    $response = [
                        'new_version' => $this->repository_info['tag_name'],
                        'slug' => $this->basename,
                        'url' => $this->theme->get('ThemeURI'),
                        'package' => $this->repository_info['zipball_url']
                    ];

                    $transient->response[ $this->basename ] = $response;
                }
            }
        }

        return $transient;
    }

    public function get_theme_info($false, $action, $response)
    {
        if (!isset($response->slug) || $response->slug != $this->basename) {
            return false;
        }

        $this->get_repository_info();

        $response->name = $this->theme->get('Name');
        $response->slug = $this->basename;
        $response->version = $this->repository_info['tag_name'];
        $response->author = $this->theme->get('Author');
        $response->author_profile = $this->theme->get('AuthorURI');
        $response->last_updated = $this->repository_info['published_at'];
        $response->homepage = $this->theme->get('ThemeURI');
        $response->short_description = $this->theme->get('Description');
        $response->sections = [
            'description' => $this->theme->get('Description'),
        ];
        $response->download_link = $this->repository_info['zipball_url'];

        return $response;
    }

    public function upgrader_source_selection($source, $remote_source, $upgrader)
    {
        global $wp_filesystem;

        if ( ! isset( $source, $remote_source ) ) {
            return $source;
        }

        if( false === stristr( basename( $source ), $this->basename ) ) {
            return $source;
        }

        $basename = basename( $source );
        $corrected_source = str_replace( $basename, $this->basename, $source );

        if ( $wp_filesystem->move( $source, $corrected_source, true ) ) {
            return $corrected_source;
        }

        return $source;
    }
}