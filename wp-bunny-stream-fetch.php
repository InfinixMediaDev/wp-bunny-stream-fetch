<?php
/*
Plugin Name: Bunny.net Stream
Description: A WordPress plugin for Bunny.net stream integration.
Version: 1.0
Author: Infinix Media Dev
*/

// Register and display the settings page
function bunny_net_stream_settings_page() {
    add_menu_page('Bunny.net Settings', 'Bunny.net Settings', 'manage_options', 'bunny-net-settings', 'bunny_net_settings_page_content');
}

// Display the settings page content
function bunny_net_settings_page_content() {
    $access_key = get_option('bunny_net_access_key');
    $library_id = get_option('bunny_net_library_id');
    $cdn_hostname = get_option('bunny_net_cdn_hostname');

    if (isset($_POST['bunny_net_submit'])) {
        update_option('bunny_net_access_key', sanitize_text_field($_POST['access_key']));
        update_option('bunny_net_library_id', sanitize_text_field($_POST['library_id']));
        update_option('bunny_net_cdn_hostname', sanitize_text_field($_POST['cdn_hostname']));
    }

    // Display the settings form
    ?>
    <div class="wrap">
        <h2>Bunny.net Settings</h2>
        <form method="post">
            <label for="access_key">Access Key:</label>
            <input type="text" id="access_key" name="access_key" value="<?php echo esc_attr($access_key); ?>">
            <br><br>
            <label for="library_id">Library ID:</label>
            <input type="text" id="library_id" name="library_id" value="<?php echo esc_attr($library_id); ?>">
            <br><br>
            <label for="cdn_hostname">CDN Hostname:</label>
            <input type="text" id="cdn_hostname" name="cdn_hostname" value="<?php echo esc_attr($cdn_hostname); ?>">
            <input type="submit" name="bunny_net_submit" class="button-primary" value="Save">
        </form>
    </div>
    <?php
}

// Hook the settings page function
add_action('admin_menu', 'bunny_net_stream_settings_page');

// Add the metabox to all post types
function add_bunny_net_metabox() {
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        add_meta_box('bunny_net_metabox', 'Bunny.net Videos', 'bunny_net_metabox_content', $post_type, 'normal', 'high');
    }
}

add_action('add_meta_boxes', 'add_bunny_net_metabox');

// Metabox content
function bunny_net_metabox_content($post) {
    // Retrieve the Access Key, Library ID, and CDN Hostname from the settings
    $access_key = get_option('bunny_net_access_key');
    $library_id = get_option('bunny_net_library_id');
    $cdn_hostname = get_option('bunny_net_cdn_hostname');

    // Fetch Bunny.net collections using the API
    $api_url = "https://video.bunnycdn.com/library/$library_id/collections";

    $response = wp_safe_remote_get($api_url, array(
        'headers' => array(
            'Accept' => 'application/json',
            'AccessKey' => $access_key,
        ),
    ));

    if (is_wp_error($response)) {
        echo 'Error fetching Bunny.net collections.';
        return;
    }

    $collections = json_decode(wp_remote_retrieve_body($response), true);

    if ($collections && isset($collections['items'])) {
        $collection_list = '<select id="bunny_net_collection" name="bunny_net_collection">';
        foreach ($collections['items'] as $collection) {
            $collection_list .= '<option value="' . esc_attr($collection['guid']) . '">' . esc_html($collection['name']) . '</option>';
        }
        $collection_list .= '</select>';

        // Display the collection dropdown
        echo $collection_list;
    }

    // JavaScript for fetching and displaying video information
    ?>
    <style>
        .bunny-net-video {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            background-color: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 10px;
        }
        .bunny-net-thumbnail {
            flex: 1;
            padding: 10px;
            max-width: 200px;
        }
        .bunny-net-thumbnail img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        .bunny-net-info {
            flex: 3;
            padding: 10px;
        }
        .bunny-net-info fieldset {
            border: 1px solid #ccc;
            border-radius: 5px;
            padding: 10px;
        }
        .bunny-net-info legend {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .bunny-net-info label {
            display: block;
            font-weight: bold;
            color: #333; /* Darker text color */
        }
        .bunny-net-info input[type="text"] {
            width: 100%;
            padding: 5px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
            color: #333; /* Darker text color */
        }
        .click-to-copy {
            cursor: pointer;
            color: blue;
            text-decoration: underline;
            margin-left: 5px;
        }
    </style>
    <script>
        // Function to copy the content of a field to the clipboard
        function copyToClipboard(text) {
            const input = document.createElement('input');
            input.style.position = 'fixed';
            input.style.opacity = 0;
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }

        document.addEventListener("DOMContentLoaded", function () {
            var savedCollection = localStorage.getItem('bunny_net_selected_collection');
            var collectionDropdown = document.getElementById('bunny_net_collection');

            if (savedCollection) {
                collectionDropdown.value = savedCollection;
                fetchVideoInfo(savedCollection);
            }

            collectionDropdown.addEventListener('change', function () {
                var selectedCollection = this.value;
                localStorage.setItem('bunny_net_selected_collection', selectedCollection);

                var videosContainer = document.getElementById('bunny_net_videos_container');
                videosContainer.innerHTML = ''; // Clear the container before fetching new data

                fetchVideoInfo(selectedCollection);
            });
        });

        function fetchVideoInfo(collectionId) {
            // Fetch video information from the selected collection
            var videoInfoUrl = "https://video.bunnycdn.com/library/<?php echo esc_js($library_id); ?>/collections/" + collectionId;
            var options = {
                method: 'GET',
                headers: {
                    accept: 'application/json',
                    AccessKey: '<?php echo esc_js($access_key); ?>',
                }
            };

            fetch(videoInfoUrl, options)
                .then(response => response.json())
                .then(response => {
                    if (response && response.previewVideoIds) {
                        var videoIds = response.previewVideoIds.split(',');

                        var videosContainer = document.getElementById('bunny_net_videos_container');
                        videosContainer.innerHTML = '';

                        if (videoIds && videoIds.length > 0) {
                            videoIds.forEach(videoId => {
                                var videoDetailsUrl = 'https://video.bunnycdn.com/library/<?php echo esc_js($library_id); ?>/videos/' + videoId;
                                fetch(videoDetailsUrl, options)
                                    .then(response => response.json())
                                    .then(video => {
                                        var videoContainer = document.createElement('div');
                                        videoContainer.classList.add('bunny-net-video');

                                        var thumbnailContainer = document.createElement('div');
                                        thumbnailContainer.classList.add('bunny-net-thumbnail');
                                        var thumbnailUrl = 'https://' + '<?php echo esc_js($cdn_hostname); ?>/' + videoId + '/' + video.thumbnailFileName;
                                        thumbnailContainer.innerHTML = '<img src="' + thumbnailUrl + '" alt="Thumbnail">';

                                        var videoInfoContainer = document.createElement('fieldset');
                                        videoInfoContainer.classList.add('bunny-net-info');

                                        var inputs = [
                                            { id: 'thumbnailUrl', label: 'Thumbnail', value: thumbnailUrl },
                                            { id: 'title', label: 'Title', value: video.title },
                                            { id: 'videoId', label: 'Video ID', value: video.guid },
                                            { id: 'hlsPlaylistUrl', label: 'HLS Playlist URL', value: 'https://<?php echo esc_js($cdn_hostname); ?>/' + videoId + '/playlist.m3u8' },
                                            { id: 'directPlayUrl', label: 'DirectPlay URL', value: 'https://iframe.mediadelivery.net/play/<?php echo esc_js($library_id); ?>/' + video.guid }
                                        ];

                                        inputs.forEach(input => {
                                            var inputField = document.createElement('input');
                                            inputField.type = 'text';
                                            inputField.id = input.id;
                                            inputField.value = input.value;
                                            inputField.disabled = true;

                                            var copyLink = document.createElement('a');
                                            copyLink.href = 'javascript:void(0)';
                                            copyLink.className = 'click-to-copy';
                                            copyLink.textContent = 'Click to Copy';
                                            copyLink.addEventListener('click', function () {
                                                copyToClipboard(input.value);
                                            });

                                            var label = document.createElement('label');
                                            label.textContent = input.label + ':';

                                            videoInfoContainer.appendChild(label);
                                            videoInfoContainer.appendChild(inputField);
                                            videoInfoContainer.appendChild(copyLink);
                                        });

                                        videoContainer.appendChild(thumbnailContainer);
                                        videoContainer.appendChild(videoInfoContainer);
                                        videosContainer.appendChild(videoContainer);
                                    })
                                    .catch(err => console.error(err));
                            });
                        } else {
                            videosContainer.innerHTML = 'No videos available in this collection.';
                        }
                    } else {
                        videosContainer.innerHTML = 'No videos available in this collection.';
                    }
                })
                .catch(err => console.error(err));
        }
    </script>
    <div id="bunny_net_videos_container"></div>
    <?php
}

// Handle form submissions
if (isset($_POST['bunny_net_submit'])) {
    update_option('bunny_net_access_key', sanitize_text_field($_POST['access_key']));
    update_option('bunny_net_library_id', sanitize_text_field($_POST['library_id']));
    update_option('bunny_net_cdn_hostname', sanitize_text_field($_POST['cdn_hostname']));
}

// Enqueue styles and scripts for the admin page
function enqueue_admin_styles_and_scripts($hook) {
    if ('toplevel_page_bunny-net-settings' == $hook) {
        wp_enqueue_style('bunny-net-admin-styles', plugin_dir_url(__FILE__) . 'admin-styles.css');
    }
}

add_action('admin_enqueue_scripts', 'enqueue_admin_styles_and_scripts');
