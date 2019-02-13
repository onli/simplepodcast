<?php

if (IN_serendipity !== true) {
    die ("Don't hack!");
}

@serendipity_plugin_api::load_language(dirname(__FILE__));

class serendipity_event_simplepodcast extends serendipity_event {
    var $title = PLUGIN_EVENT_SIMPLEPODCAST_NAME;

    function introspect(&$propbag) {
        global $serendipity;

        $propbag->add('name',          PLUGIN_EVENT_SIMPLEPODCAST_NAME);
        $propbag->add('description',   PLUGIN_EVENT_SIMPLEPODCAST_DESC);
        $propbag->add('stackable',     false);
        $propbag->add('author',        'onli');
        $propbag->add('version',       '0.1');
        $propbag->add('requirements',  array(
            'serendipity' => '2.1'
        ));
        $propbag->add('event_hooks',   array('frontend_display' => true,
                                                'frontend_header' => true,
                                                'frontend_display:rss-2.0:per_entry' => true,
                                                'frontend_display:rss-1.0:per_entry' => true,
                                                'frontend_display:rss-0.91:per_entry' => true
                                            ));
        $propbag->add('groups', array('MARKUP'));

        $this->markup_elements = array(
            array(
              'name'     => 'ENTRY_BODY',
              'element'  => 'body',
            ),
            array(
              'name'     => 'EXTENDED_BODY',
              'element'  => 'extended',
            )
        );

        $conf_array = array();
        foreach($this->markup_elements as $element) {
            $conf_array[] = $element['name'];
        }
        $propbag->add('configuration', $conf_array);
    }

    function install() {
        serendipity_plugin_api::hook_event('backend_cache_entries', $this->title);
    }

    function uninstall(&$propbag) {
        serendipity_plugin_api::hook_event('backend_cache_purge', $this->title);
        serendipity_plugin_api::hook_event('backend_cache_entries', $this->title);
    }

    function generate_content(&$title) {
        $title = $this->title;
    }


    function introspect_config_item($name, &$propbag) {
        $propbag->add('type',        'boolean');
        $propbag->add('name',        constant($name));
        $propbag->add('description', sprintf(APPLY_MARKUP_TO, constant($name)));
        $propbag->add('default', 'true');
        return true;
    }


    function event_hook($event, &$bag, &$eventData, $addData = null) {
        global $serendipity;

        $hooks = &$bag->get('event_hooks');

        if (isset($hooks[$event])) {
            switch($event) {
                case 'frontend_display':
                    foreach ($this->markup_elements as $temp) {
                        if (serendipity_db_bool($this->get_config($temp['name'], true)) && isset($eventData[$temp['element']]) &&
                            !$eventData['properties']['ep_disable_markup_' . $this->instance] &&
                            !isset($serendipity['POST']['properties']['disable_markup_' . $this->instance])) {
                            $element = $temp['element'];
                            $eventData[$element] = $this->podlove_markup($eventData[$element], $eventData);
                        }
                    }
                    return true;
                    break;
                case 'frontend_header':
                        echo '<script src="https://cdn.podlove.org/web-player/embed.js"></script>';
                    return true;
                    break;
                case 'frontend_display:rss-2.0:per_entry':
                case 'frontend_display:rss-1.0:per_entry':
                case 'frontend_display:rss-0.91:per_entry':
                    $links = $this->getPodcastLinks($eventData['body']);
                    if ($links[0] !== null) {
                        $link = $links[0];
                        $filetype = preg_replace("@.+\.(....?)@", "$1", $link);
                        $eventData['display_dat'] = "<enclosure url=\"$link\" type=\"audio/$filetype\" />";
                    }
                    return true;
                    break;
                default:
                    return false;
            }
        } else {
            return false;
        }
    }

    function getPodcastLinks($text) {
        preg_match_all("@<a[^>]+href=[\"'](?<link>$podcastPath.+)[\"']@U", $text, $matches);
        return $matches['link'];
    }

    function podlove_markup($text, $entry) {
        global $serendipity;

        $podcastPath = $serendipity['serendipityHTTPPath'] . $serendipity['uploadHTTPPath'] . 'podcasts/';
        // If body or extended body contain link to file in uploads/podcast
        
        $links = $this->getPodcastLinks($text);
        if ($links[0] !== null) {
            // Enter here only if we found a podcast link
            // But we also need some meta tags
            $config = $this->generateConfig($entry, $links);

            // We add a script tag initializing the podlove webplayer for all those links
            $script = "<div id=\"example\"></div><script>
                podlovePlayer('#example', $config);
            </script>";
        }

        return $text . $script;
    }

    function generateConfig($entry, $links) {
        global $serendipity;

        #TODO: Get file properties by reading the id3 tags etc by reading the file on disk
        
        $title = json_encode($entry['title']);
        $summary = json_encode($entry['body']);
        $show = json_encode($serendipity['blogTitle']);
        $publicationDate = json_encode($entry['date']);
        $url = json_encode($serendipity['baseURL']);
        
        $out = "{
        title: $title,
        summary: $summary,
        publicationDate: $publicationDate,
        show: {
            title: $show,
            url: $url
        },
        audio: [";
        foreach ($links as $link) {
            $filetype = preg_replace("@.+\.(....?)@", "$1", $link);
            $out .= "{
              url: '$link',
              mimeType: 'audio/$filetype',
              title: 'Audio $filetype'
            },";
        }
        $out .= "],
        reference: {
            config: '//podlove-player.surge.sh/fixtures/example.json',
            share: '//podlove-player.surge.sh/share'
        },
        contributors: [{
          name: '${entry['author']}',
          comment: null
        }]
    }";
        return $out;
    }

    

    function debugMsg($msg) {
        global $serendipity;
        
        $this->debug_fp = @fopen ( $serendipity ['serendipityPath'] . 'templates_c/pluginname.log', 'a' );
        if (! $this->debug_fp) {
            return false;
        }
        
        if (empty ( $msg )) {
            fwrite ( $this->debug_fp, "failure \
" );
        } else {
            fwrite ( $this->debug_fp, print_r ( $msg, true ) );
        }
        fclose ( $this->debug_fp );
    }

}

/* vim: set sts=4 ts=4 expandtab : */
?>