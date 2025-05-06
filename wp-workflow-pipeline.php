<?php
/*
Plugin Name: WordPress Workflow Pipeline
Description: A pipeline automation plugin for WordPress with dynamic steps and advanced UI/UX.
Version: 1.3.8
Author: Shahin Ilderemi
Author URI: https://ildrm.com
Text Domain: wordpress-workflow-pipeline
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WordPress_Workflow_Pipeline {
    private $plugin_name = 'wordpress-workflow-pipeline';
    private $version = '1.3.8';

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_ajax_wwp_save_pipeline', [$this, 'save_pipeline']);
        add_action('wp_ajax_wwp_run_pipeline', [$this, 'run_pipeline']);
        add_action('wp_ajax_wwp_get_logs', [$this, 'get_logs']);
        add_action('wp_ajax_wwp_delete_pipeline', [$this, 'delete_pipeline']);
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        add_action('init', [$this, 'init'], 10);
    }

    public function load_textdomain() {
        load_plugin_textdomain($this->plugin_name, false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wwp_pipelines';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            config text NOT NULL,
            cron_schedule varchar(50) DEFAULT '',
            last_run datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'inactive',
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $log_dir = WP_CONTENT_DIR . '/wwp_logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }

    public function init() {
        $this->load_textdomain();
        $this->schedule_cron_tasks();
    }

    public function add_cron_schedules($schedules) {
        $schedules['wwp_five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', $this->plugin_name)
        ];
        $schedules['wwp_hourly'] = [
            'interval' => 3600,
            'display' => __('Hourly', $this->plugin_name)
        ];
        $schedules['wwp_daily'] = [
            'interval' => 86400,
            'display' => __('Daily', $this->plugin_name)
        ];
        return $schedules;
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Workflow Pipelines', $this->plugin_name),
            __('Workflow Pipelines', $this->plugin_name),
            'manage_options',
            $this->plugin_name,
            [$this, 'render_admin_page'],
            'dashicons-randomize'
        );
    }

    public function render_admin_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wwp_pipelines';
        $pipelines = $wpdb->get_results("SELECT * FROM $table_name");
        $post_types = get_post_types(['public' => true], 'objects');
        ?>
        <div class="wrap wwp-wrap">
            <h1><?php _e('WordPress Workflow Pipelines', $this->plugin_name); ?></h1>
            
            <h2><?php _e('Pipelines', $this->plugin_name); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Name', $this->plugin_name); ?></th>
                        <th><?php _e('Status', $this->plugin_name); ?></th>
                        <th><?php _e('Cron Schedule', $this->plugin_name); ?></th>
                        <th><?php _e('Last Run', $this->plugin_name); ?></th>
                        <th><?php _e('Actions', $this->plugin_name); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pipelines as $pipeline): ?>
                        <tr>
                            <td><?php echo esc_html($pipeline->name); ?></td>
                            <td><?php echo esc_html($pipeline->status); ?></td>
                            <td><?php echo esc_html($pipeline->cron_schedule); ?></td>
                            <td><?php echo $pipeline->last_run ? esc_html($pipeline->last_run) : '-'; ?></td>
                            <td>
                                <button class="button wwp-edit-pipeline" data-id="<?php echo esc_attr($pipeline->id); ?>" title="<?php _e('Edit', $this->plugin_name); ?>">✎</button>
                                <button class="button wwp-run-pipeline" data-id="<?php echo esc_attr($pipeline->id); ?>" title="<?php _e('Run Now', $this->plugin_name); ?>">▶</button>
                                <button class="button wwp-view-logs" data-id="<?php echo esc_attr($pipeline->id); ?>" title="<?php _e('View Logs', $this->plugin_name); ?>">📜</button>
                                <button class="button wwp-delete-pipeline" data-id="<?php echo esc_attr($pipeline->id); ?>" title="<?php _e('Delete', $this->plugin_name); ?>">🗑</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h2><?php _e('Create/Edit Pipeline', $this->plugin_name); ?></h2>
            <form id="wwp-pipeline-form" method="post">
                <input type="hidden" name="pipeline_id" id="pipeline_id">
                <table class="form-table">
                    <tr>
                        <th><label for="pipeline_name"><?php _e('Pipeline Name', $this->plugin_name); ?></label></th>
                        <td>
                            <input type="text" name="pipeline_name" id="pipeline_name" class="regular-text" required>
                            <p class="description"><?php _e('Example: "Sync Products from API"', $this->plugin_name); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cron_schedule"><?php _e('Cron Schedule', $this->plugin_name); ?></label></th>
                        <td>
                            <select name="cron_schedule" id="cron_schedule">
                                <option value=""><?php _e('Manual', $this->plugin_name); ?></option>
                                <option value="wwp_five_minutes"><?php _e('Every 5 Minutes', $this->plugin_name); ?></option>
                                <option value="wwp_hourly"><?php _e('Hourly', $this->plugin_name); ?></option>
                                <option value="wwp_daily"><?php _e('Daily', $this->plugin_name); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="enable_logging"><?php _e('Enable Logging', $this->plugin_name); ?></label></th>
                        <td><input type="checkbox" name="enable_logging" id="enable_logging" value="1"></td>
                    </tr>
                </table>

                <h3><?php _e('Pipeline Steps', $this->plugin_name); ?></h3>
                <div id="wwp-steps-container">
                </div>
                <button type="button" class="button wwp-add-step"><?php _e('Add Step', $this->plugin_name); ?></button>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php _e('Save Pipeline', $this->plugin_name); ?>">
                </p>
            </form>

            <div id="wwp-logs-modal" class="wwp-modal" style="display:none;">
                <div class="wwp-modal-content">
                    <span class="wwp-modal-close">×</span>
                    <h2><?php _e('Pipeline Logs', $this->plugin_name); ?></h2>
                    <div id="wwp-logs-content"></div>
                </div>
            </div>
        </div>

        <script>
        (function($) {
            'use strict';

            // Ensure jQuery is available
            if (typeof $ === 'undefined') {
                console.error('WWP: jQuery is not defined');
                return;
            }

            console.log('WWP: Initializing pipeline form');

            // Action types configuration
            const actionTypes = {
                'fetch_data': {
                    name: 'Fetch Data',
                    fields: {
                        url: { 
                            type: 'url', 
                            label: 'Source URL',
                            example: 'https://api.example.com/products'
                        },
                        format: { 
                            type: 'select', 
                            label: 'Data Format',
                            options: {
                                html: 'HTML',
                                json: 'JSON',
                                xml: 'XML'
                            }
                        },
                        selectors: { 
                            type: 'textarea', 
                            label: 'HTML/XML Selectors', 
                            depends_on: { format: ['html', 'xml'] },
                            example: '{"title": "//h1", "price": "//div[@class=\'price\']"}'
                        },
                        json_paths: { 
                            type: 'textarea', 
                            label: 'JSON Paths', 
                            depends_on: { format: ['json'] },
                            example: '{"name": "products[0].name", "price": "products[0].price"}'
                        }
                    }
                },
                'php_function': {
                    name: 'PHP Function',
                    fields: {
                        function_name: { 
                            type: 'text', 
                            label: 'Function Name',
                            example: 'strtoupper'
                        },
                        parameters: { 
                            type: 'textarea', 
                            label: 'Parameters (JSON)',
                            example: '{"text": "hello world"}'
                        }
                    }
                },
                'wp_function': {
                    name: 'WordPress Function',
                    fields: {
                        function_name: { 
                            type: 'select', 
                            label: 'WordPress Function',
                            options: {
                                'wp_insert_post': 'Insert Post',
                                'wp_set_post_terms': 'Set Post Terms',
                                'update_post_meta': 'Update Post Meta'
                            }
                        },
                        parameters: { 
                            type: 'textarea', 
                            label: 'Parameters (JSON)',
                            example: '{"post_title": "New Post", "post_content": "Content", "post_status": "publish"}'
                        }
                    }
                },
                'wc_function': {
                    name: 'WooCommerce Function',
                    fields: {
                        function_name: { 
                            type: 'select', 
                            label: 'WooCommerce Function',
                            options: {
                                'wc_create_product': 'Create Product',
                                'wc_update_product': 'Update Product'
                            }
                        },
                        parameters: { 
                            type: 'textarea', 
                            label: 'Parameters (JSON)',
                            example: '{"name": "New Product", "regular_price": "99.99"}'
                        }
                    }
                }
            };

            let stepCount = 0;

            // Function to add a new step
            function addStep(stepData = {}) {
                stepCount++;
                const stepId = 'step_' + stepCount;
                const stepHtml = `
                    <div class="wwp-step" data-step-id="${stepId}">
                        <h4>Step ${stepCount} <button type="button" class="button wwp-remove-step">🗑</button></h4>
                        <table class="form-table">
                            <tr>
                                <th><label>Action Type</label></th>
                                <td>
                                    <select name="steps[${stepId}][action_type]" class="wwp-action-type">
                                        <option value="">Select Action Type</option>
                                        ${Object.keys(actionTypes).map(type => 
                                            `<option value="${type}" ${stepData.action_type === type ? 'selected' : ''}>${actionTypes[type].name}</option>`
                                        ).join('')}
                                    </select>
                                </td>
                            </tr>
                            <tr class="wwp-action-fields">
                                <td colspan="2">
                                    <div class="wwp-action-fields-container"></div>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Input Mapping</label></th>
                                <td>
                                    <textarea name="steps[${stepId}][input_mapping]" class="wwp-input-mapping large-text" rows="4">${stepData.input_mapping || ''}</textarea>
                                    <p class="description">Example: {"input_field": "previous_step_output_key"}</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label>Output Key</label></th>
                                <td>
                                    <input type="text" name="steps[${stepId}][output_key]" class="wwp-output-key regular-text" value="${stepData.output_key || ''}">
                                    <p class="description">Example: "step1_result"</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                `;
                $('#wwp-steps-container').append(stepHtml);
                if (stepData.action_type) {
                    const $step = $(`[data-step-id="${stepId}"]`);
                    $step.find('.wwp-action-type').val(stepData.action_type).trigger('change');
                    Object.entries(stepData.config || {}).forEach(([key, value]) => {
                        $step.find(`[name="steps[${stepId}][config][${key}]"]`).val(
                            typeof value === 'object' ? JSON.stringify(value) : value
                        );
                    });
                }
            }

            // Add step button handler
            $('.wwp-add-step').on('click', () => {
                console.log('WWP: Add step button clicked');
                addStep();
            });

            // Remove step button handler
            $(document).on('click', '.wwp-remove-step', function() {
                console.log('WWP: Remove step button clicked');
                $(this).closest('.wwp-step').remove();
            });

            // Action type change handler
            $(document).on('change', '.wwp-action-type', function() {
                console.log('WWP: Action type changed', $(this).val());
                const $step = $(this).closest('.wwp-step');
                const stepId = $step.data('step-id');
                const actionType = $(this).val();
                const $container = $step.find('.wwp-action-fields-container');
                $container.empty();

                if (actionType && actionTypes[actionType]) {
                    const fields = actionTypes[actionType].fields;
                    Object.entries(fields).forEach(([key, field]) => {
                        let fieldHtml = '';
                        const dependsOn = field.depends_on || {};

                        if (!Object.keys(dependsOn).length || dependsOn.format.includes($step.find('[name$="[config][format]"]').val())) {
                            fieldHtml = `
                                <div class="wwp-field">
                                    <label>${field.label}</label>
                            `;
                            if (field.type === 'text' || field.type === 'url') {
                                fieldHtml += `<input type="${field.type}" name="steps[${stepId}][config][${key}]" class="regular-text${field.type === 'url' ? ' wwp-url-input' : ''}">`;
                            } else if (field.type === 'textarea') {
                                fieldHtml += `<textarea name="steps[${stepId}][config][${key}]" class="large-text" rows="4"></textarea>`;
                            } else if (field.type === 'select') {
                                fieldHtml += `
                                    <select name="steps[${stepId}][config][${key}]">
                                        ${Object.entries(field.options).map(([value, label]) => 
                                            `<option value="${value}">${label}</option>`
                                        ).join('')}
                                    </select>
                                `;
                            }
                            fieldHtml += field.example ? `<p class="description">Example: ${field.example}</p>` : '';
                            fieldHtml += '</div>';
                            $container.append(fieldHtml);
                        }
                    });
                }
            });

            // Form submission handler
            $('#wwp-pipeline-form').on('submit', function(e) {
                console.log('WWP: Form submission triggered');
                e.preventDefault();

                try {
                    let isValid = true;
                    let errorMessage = '';

                    if (!$('#pipeline_name').val().trim()) {
                        errorMessage = 'Pipeline Name is required.';
                        isValid = false;
                    }

                    $('.wwp-url-input').each(function() {
                        const url = $(this).val().trim();
                        const urlPattern = /^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/;
                        if (url && !urlPattern.test(url)) {
                            errorMessage = 'Please enter a valid URL. Example: https://api.example.com/products';
                            $(this).focus();
                            isValid = false;
                            return false;
                        }
                    });

                    $('.wwp-step').each(function() {
                        const $step = $(this);
                        const stepId = $step.data('step-id');
                        const $selectors = $step.find(`[name="steps[${stepId}][config][selectors]"]`);
                        const $jsonPaths = $step.find(`[name="steps[${stepId}][config][json_paths]"]`);
                        const $parameters = $step.find(`[name="steps[${stepId}][config][parameters]"]`);

                        function validateJson($field, fieldName) {
                            if ($field.length && $field.val().trim()) {
                                try {
                                    JSON.parse($field.val());
                                } catch (e) {
                                    errorMessage = `Invalid JSON in ${fieldName}: ${e.message}`;
                                    $field.focus();
                                    isValid = false;
                                    return false;
                                }
                            }
                            return true;
                        }

                        if (!validateJson($selectors, 'HTML/XML Selectors')) return;
                        if (!validateJson($jsonPaths, 'JSON Paths')) return;
                        if (!validateJson($parameters, 'Parameters')) return;
                    });

                    if (!isValid) {
                        console.error('WWP: Validation failed:', errorMessage);
                        alert(errorMessage);
                        return;
                    }

                    const steps = [];
                    $('.wwp-step').each(function() {
                        const $step = $(this);
                        const stepId = $step.data('step-id');
                        const step = {
                            action_type: $step.find('.wwp-action-type').val(),
                            input_mapping: $step.find('.wwp-input-mapping').val(),
                            output_key: $step.find('.wwp-output-key').val(),
                            config: {}
                        };
                        $step.find('[name^=steps][name*=config]').each(function() {
                            const name = $(this).attr('name').match(/\[config\]\[(.+)\]/)[1];
                            let value = $(this).val().trim();
                            try {
                                value = value ? JSON.parse(value) : value;
                            } catch (e) {
                                // Keep as string if not valid JSON
                            }
                            step.config[name] = value;
                        });
                        steps.push(step);
                    });

                    const formData = {
                        action: 'wwp_save_pipeline',
                        nonce: '<?php echo wp_create_nonce('wwp_nonce'); ?>',
                        pipeline_id: $('#pipeline_id').val(),
                        pipeline_name: $('#pipeline_name').val().trim(),
                        cron_schedule: $('#cron_schedule').val(),
                        enable_logging: $('#enable_logging').is(':checked') ? 1 : 0,
                        steps: JSON.stringify(steps)
                    };

                    console.log('WWP: Sending AJAX request:', formData);

                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            console.log('WWP: AJAX response:', response);
                            if (response.success) {
                                alert('Pipeline saved successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + (response.data?.message || 'Unknown server error'));
                                console.error('WWP: Server response:', response);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('WWP: AJAX error:', xhr.responseText, status, error);
                            alert('Error saving pipeline: ' + error + ' (Status: ' + xhr.status + ')');
                        }
                    });
                } catch (error) {
                    console.error('WWP: Form submission error:', error);
                    alert('Error in form submission: ' + error.message);
                }
            });

            // Edit pipeline handler
            $(document).on('click', '.wwp-edit-pipeline', function(e) {
                e.preventDefault();
                console.log('WWP: Edit pipeline button clicked', $(this).data('id'));
                const pipelineId = $(this).data('id');
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wwp_save_pipeline',
                        nonce: '<?php echo wp_create_nonce('wwp_nonce'); ?>',
                        pipeline_id: pipelineId,
                        get_pipeline: true
                    },
                    success: function(response) {
                        console.log('WWP: Edit pipeline response:', response);
                        if (response.success) {
                            const pipeline = response.data.pipeline;
                            $('#pipeline_id').val(pipeline.id);
                            $('#pipeline_name').val(pipeline.name);
                            $('#cron_schedule').val(pipeline.cron_schedule);
                            $('#enable_logging').prop('checked', pipeline.config.enable_logging);
                            $('#wwp-steps-container').empty();
                            pipeline.config.steps.forEach(step => {
                                addStep({
                                    action_type: step.action_type,
                                    input_mapping: step.input_mapping,
                                    output_key: step.output_key,
                                    config: step.config
                                });
                            });
                            $('html, body').animate({
                                scrollTop: $('#wwp-pipeline-form').offset().top
                            }, 500);
                        } else {
                            console.error('WWP: Edit pipeline error:', response.data.message);
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('WWP: AJAX error:', xhr.responseText, status, error);
                        alert('Error loading pipeline: ' + error);
                    }
                });
            });

            // Run pipeline handler
            $(document).on('click', '.wwp-run-pipeline', function(e) {
                e.preventDefault();
                console.log('WWP: Run pipeline button clicked', $(this).data('id'));
                const pipelineId = $(this).data('id');
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wwp_run_pipeline',
                        nonce: '<?php echo wp_create_nonce('wwp_nonce'); ?>',
                        pipeline_id: pipelineId
                    },
                    success: function(response) {
                        console.log('WWP: Run pipeline response:', response);
                        alert(response.success ? 'Pipeline executed successfully!' : 'Error: ' + response.data.message);
                    },
                    error: function(xhr, status, error) {
                        console.error('WWP: AJAX error:', xhr.responseText, status, error);
                        alert('Error running pipeline: ' + error);
                    }
                });
            });

            // View logs handler
            $(document).on('click', '.wwp-view-logs', function(e) {
                e.preventDefault();
                console.log('WWP: View logs button clicked', $(this).data('id'));
                const pipelineId = $(this).data('id');
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wwp_get_logs',
                        nonce: '<?php echo wp_create_nonce('wwp_nonce'); ?>',
                        pipeline_id: pipelineId
                    },
                    success: function(response) {
                        console.log('WWP: View logs response:', response);
                        if (response.success) {
                            $('#wwp-logs-content').html('<pre>' + response.data.logs + '</pre>');
                            $('#wwp-logs-modal').show();
                        } else {
                            console.error('WWP: View logs error:', response.data.message);
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('WWP: AJAX error:', xhr.responseText, status, error);
                        alert('Error loading logs: ' + error);
                    }
                });
            });

            // Delete pipeline handler
            $(document).on('click', '.wwp-delete-pipeline', function(e) {
                e.preventDefault();
                console.log('WWP: Delete pipeline button clicked', $(this).data('id'));
                if (!confirm('Are you sure you want to delete this pipeline?')) {
                    return;
                }
                const pipelineId = $(this).data('id');
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'wwp_delete_pipeline',
                        nonce: '<?php echo wp_create_nonce('wwp_nonce'); ?>',
                        pipeline_id: pipelineId
                    },
                    success: function(response) {
                        console.log('WWP: Delete pipeline response:', response);
                        if (response.success) {
                            alert('Pipeline deleted successfully!');
                            location.reload();
                        } else {
                            console.error('WWP: Delete pipeline error:', response.data.message);
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('WWP: AJAX error:', xhr.responseText, status, error);
                        alert('Error deleting pipeline: ' + error);
                    }
                });
            });

            // Modal close handler
            $('.wwp-modal-close').on('click', () => {
                console.log('WWP: Modal close button clicked');
                $('#wwp-logs-modal').hide();
            });

            console.log('WWP: Event listeners initialized');
        })(jQuery);
        </script>

        <style>
        .wwp-wrap { max-width: 1200px; margin: 0 auto; }
        .wwp-step { 
            border: 1px solid #ddd; 
            padding: 15px; 
            margin-bottom: 20px; 
            background: #fff; 
            border-radius: 4px; 
        }
        .wwp-step h4 { margin-top: 0; }
        .wwp-field { margin-bottom: 15px; }
        .wwp-field label { display: block; margin-bottom: 5px; font-weight: bold; }
        .wwp-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .wwp-modal-content {
            background: #fff;
            margin: 5% auto;
            padding: 20px;
            width: 80%;
            max-width: 800px;
            border-radius: 4px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .wwp-modal-close {
            float: right;
            font-size: 20px;
            cursor: pointer;
        }
        .wwp-action-fields-container { padding: 10px; }
        .wwp-field textarea, .wwp-field input[type="text"], .wwp-field input[type="url"] {
            width: 100%;
            max-width: 500px;
        }
        .wwp-edit-pipeline, .wwp-run-pipeline, .wwp-view-logs, .wwp-delete-pipeline {
            margin-right: 5px;
        }
        .description { color: #666; font-size: 12px; margin-top: 5px; }
        </style>
        <?php
    }

    public function save_pipeline() {
        $log = [];
        $log[] = 'Received save_pipeline request: ' . json_encode($_POST);

        if (!check_ajax_referer('wwp_nonce', 'nonce', false)) {
            $log[] = 'Nonce verification failed';
            $this->save_log(0, $log);
            wp_send_json_error(['message' => 'Security check failed'], 403);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wwp_pipelines';
        $pipeline_id = isset($_POST['pipeline_id']) ? absint($_POST['pipeline_id']) : 0;

        // Handle get_pipeline request
        if (isset($_POST['get_pipeline'])) {
            if ($pipeline_id <= 0) {
                $log[] = 'Invalid pipeline ID for get_pipeline';
                $this->save_log(0, $log);
                wp_send_json_error(['message' => 'Invalid pipeline ID'], 400);
            }

            $pipeline = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $pipeline_id));
            if (!$pipeline) {
                $log[] = 'Pipeline not found: ID ' . $pipeline_id;
                $this->save_log($pipeline_id, $log);
                wp_send_json_error(['message' => 'Pipeline not found'], 404);
            }

            $pipeline->config = json_decode($pipeline->config, true);
            $log[] = 'Retrieved pipeline: ID ' . $pipeline_id;
            $this->save_log($pipeline_id, $log);
            wp_send_json_success(['pipeline' => $pipeline]);
        }

        // Handle save pipeline request
        $pipeline_name = isset($_POST['pipeline_name']) ? sanitize_text_field($_POST['pipeline_name']) : '';
        $cron_schedule = isset($_POST['cron_schedule']) ? sanitize_text_field($_POST['cron_schedule']) : '';
        $enable_logging = isset($_POST['enable_logging']) ? 1 : 0;
        $steps_raw = isset($_POST['steps']) ? stripslashes($_POST['steps']) : '';

        if (empty($pipeline_name)) {
            $log[] = 'Pipeline name is required';
            $this->save_log($pipeline_id, $log);
            wp_send_json_error(['message' => 'Pipeline name is required'], 400);
        }

        $steps = json_decode($steps_raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $log[] = 'Invalid steps JSON: ' . json_last_error_msg();
            $this->save_log($pipeline_id, $log);
            wp_send_json_error(['message' => 'Invalid steps JSON: ' . json_last_error_msg()], 400);
        }

        if (empty($steps)) {
            $log[] = 'At least one step is required';
            $this->save_log($pipeline_id, $log);
            wp_send_json_error(['message' => 'At least one step is required'], 400);
        }

        foreach ($steps as $index => $step) {
            if (empty($step['action_type'])) {
                $log[] = "Step $index: Action type is required";
                $this->save_log($pipeline_id, $log);
                wp_send_json_error(['message' => "Step $index: Action type is required"], 400);
            }
            if (empty($step['output_key'])) {
                $log[] = "Step $index: Output key is required";
                $this->save_log($pipeline_id, $log);
                wp_send_json_error(['message' => "Step $index: Output key is required"], 400);
            }
            if (!isset($step['input_mapping'])) {
                $steps[$index]['input_mapping'] = ''; // Default to empty string
                $log[] = "Step $index: input_mapping was missing, defaulted to empty string";
            }
            if (!isset($step['config']) || !is_array($step['config'])) {
                $log[] = "Step $index: Config is required and must be an array";
                $this->save_log($pipeline_id, $log);
                wp_send_json_error(['message' => "Step $index: Config is required and must be an array"], 400);
            }
        }

        $config = [
            'enable_logging' => $enable_logging,
            'steps' => $steps
        ];

        $data = [
            'name' => $pipeline_name,
            'config' => json_encode($config),
            'cron_schedule' => $cron_schedule,
            'status' => $cron_schedule ? 'active' : 'inactive'
        ];

        try {
            if ($pipeline_id) {
                $result = $wpdb->update($table_name, $data, ['id' => $pipeline_id]);
                if ($result === false) {
                    throw new Exception('Database update failed');
                }
            } else {
                $result = $wpdb->insert($table_name, $data);
                if ($result === false) {
                    throw new Exception('Database insert failed');
                }
                $pipeline_id = $wpdb->insert_id;
            }
        } catch (Exception $e) {
            $log[] = 'Database error: ' . $e->getMessage();
            $this->save_log($pipeline_id, $log);
            wp_send_json_error(['message' => 'Database error: ' . $e->getMessage()], 500);
        }

        $this->update_cron_schedule($pipeline_id, $cron_schedule);

        $log[] = 'Pipeline saved successfully: ID ' . $pipeline_id;
        $this->save_log($pipeline_id, $log);
        wp_send_json_success();
    }

    public function delete_pipeline() {
        check_ajax_referer('wwp_nonce', 'nonce');
        global $wpdb;
        $table_name = $wpdb->prefix . 'wwp_pipelines';
        $pipeline_id = absint($_POST['pipeline_id']);

        wp_clear_scheduled_hook('wwp_run_pipeline_' . $pipeline_id);
        $result = $wpdb->delete($table_name, ['id' => $pipeline_id]);

        if ($result) {
            $log_file = WP_CONTENT_DIR . '/wwp_logs/pipeline_' . $pipeline_id . '.log';
            if (file_exists($log_file)) {
                unlink($log_file);
            }
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Failed to delete pipeline']);
        }
    }

    private function update_cron_schedule($pipeline_id, $schedule) {
        wp_clear_scheduled_hook('wwp_run_pipeline_' . $pipeline_id);
        if ($schedule) {
            wp_schedule_event(time(), $schedule, 'wwp_run_pipeline_' . $pipeline_id);
        }
    }

    private function schedule_cron_tasks() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wwp_pipelines';
        $pipelines = $wpdb->get_results("SELECT id, cron_schedule FROM $table_name WHERE status = 'active'");
        
        foreach ($pipelines as $pipeline) {
            if ($pipeline->cron_schedule && !wp_next_scheduled('wwp_run_pipeline_' . $pipeline->id)) {
                wp_schedule_event(time(), $pipeline->cron_schedule, 'wwp_run_pipeline_' . $pipeline->id);
            }
        }
    }

    public function run_pipeline() {
        check_ajax_referer('wwp_nonce', 'nonce');
        $pipeline_id = absint($_POST['pipeline_id']);
        $result = $this->execute_pipeline($pipeline_id);
        wp_send_json($result);
    }

    public function get_logs() {
        check_ajax_referer('wwp_nonce', 'nonce');
        $pipeline_id = absint($_POST['pipeline_id']);
        $log_file = WP_CONTENT_DIR . '/wwp_logs/pipeline_' . $pipeline_id . '.log';
        
        if (file_exists($log_file)) {
            $logs = file_get_contents($log_file);
            wp_send_json_success(['logs' => esc_html($logs)]);
        } else {
            wp_send_json_error(['message' => 'No logs found']);
        }
    }

    private function execute_pipeline($pipeline_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wwp_pipelines';
        $pipeline = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $pipeline_id));
        
        if (!$pipeline) {
            return ['success' => false, 'data' => ['message' => 'Pipeline not found']];
        }

        $config = json_decode($pipeline->config, true);
        $log = [];
        $context = [];

        foreach ($config['steps'] as $step_index => $step) {
            $input_data = $this->map_inputs($step['input_mapping'], $context);
            $log[] = "Step $step_index input data: " . json_encode($input_data);
            $result = $this->execute_step($step, $input_data, $log);
            
            if ($result['success'] && $step['output_key']) {
                $context[$step['output_key']] = $result['data'];
                $log[] = "Step $step_index output stored in context: " . json_encode([$step['output_key'] => $result['data']]);
            }
            
            if (!$result['success']) {
                $log[] = "Step $step_index failed: " . $result['message'];
                break;
            }
        }

        $wpdb->update($table_name, ['last_run' => current_time('mysql')], ['id' => $pipeline_id]);

        if ($config['enable_logging']) {
            $this->save_log($pipeline_id, $log);
        }

        return ['success' => true, 'data' => ['message' => 'Pipeline executed successfully']];
    }

    private function map_inputs($mapping, $context) {
        if (!$mapping) {
            return $context;
        }

        $mappings = is_array($mapping) ? $mapping : json_decode($mapping, true);
        $result = [];

        foreach ($mappings as $input_key => $context_key) {
            if (isset($context[$context_key])) {
                $result[$input_key] = $context[$context_key];
            }
        }

        return $result;
    }

    private function execute_step($step, $input_data, &$log) {
        if (!did_action('init')) {
            $log[] = 'Step execution attempted before init';
            return ['success' => false, 'message' => 'Step execution too early'];
        }

        switch ($step['action_type']) {
            case 'fetch_data':
                return $this->execute_fetch_data($step['config'], $log);
            case 'php_function':
                return $this->execute_php_function($step['config'], $input_data, $log);
            case 'wp_function':
                return $this->execute_wp_function($step['config'], $input_data, $log);
            case 'wc_function':
                return $this->execute_wc_function($step['config'], $input_data, $log);
            default:
                $log[] = 'Invalid action type: ' . $step['action_type'];
                return ['success' => false, 'message' => 'Invalid action type'];
        }
    }

    private function execute_fetch_data($config, &$log) {
        if (empty($config['url']) || !filter_var($config['url'], FILTER_VALIDATE_URL)) {
            $log[] = 'Invalid URL provided: ' . ($config['url'] ?? 'empty');
            return ['success' => false, 'message' => 'Invalid URL provided'];
        }

        $response = wp_remote_get($config['url'], [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html'
            ]
        ]);
        if (is_wp_error($response)) {
            $log[] = 'Error fetching URL ' . $config['url'] . ': ' . $response->get_error_message();
            return ['success' => false, 'message' => 'Failed to fetch URL'];
        }

        $body = wp_remote_retrieve_body($response);
        $items = [];

        switch ($config['format']) {
            case 'html':
                $items = $this->parse_html($body, is_array($config['selectors']) ? $config['selectors'] : json_decode($config['selectors'], true), $log);
                break;
            case 'xml':
                $items = $this->parse_xml($body, is_array($config['selectors']) ? $config['selectors'] : json_decode($config['selectors'], true), $log);
                break;
            case 'json':
                $items = $this->parse_json($body, is_array($config['json_paths']) ? $config['json_paths'] : json_decode($config['json_paths'], true), $log);
                break;
        }

        $log[] = 'Fetched data: ' . json_encode($items);
        return ['success' => true, 'data' => $items];
    }

    private function execute_php_function($config, $input_data, &$log) {
        $function_name = $config['function_name'];
        $parameters = (is_array($config['parameters']) ? $config['parameters'] : (json_decode($config['parameters'], true) ?? []));
        
        if (!function_exists($function_name)) {
            $log[] = "PHP function $function_name does not exist";
            return ['success' => false, 'message' => 'Function does not exist'];
        }

        try {
            $result = call_user_func_array($function_name, array_merge($parameters, $input_data));
            $log[] = "PHP function $function_name executed successfully";
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            $log[] = "Error executing PHP function $function_name: " . $e->getMessage();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function execute_wp_function($config, $input_data, &$log) {
        $function_name = $config['function_name'];
        $parameters = (is_array($config['parameters']) ? $config['parameters'] : (json_decode($config['parameters'], true) ?? []));

        if (!did_action('init')) {
            $log[] = "WP function $function_name attempted before init";
            return ['success' => false, 'message' => 'Function execution too early'];
        }

        try {
            switch ($function_name) {
                case 'wp_insert_post':
                    $post_ids = [];
                    // Extract the list from 'data' key if it exists, otherwise use input_data directly
                    $input_list = isset($input_data['data']) && is_array($input_data['data']) ? $input_data['data'] : (is_array($input_data) ? $input_data : [$input_data]);
                    
                    $log[] = 'Input data for wp_insert_post: ' . json_encode($input_list);

                    foreach ($input_list as $index => $item_data) {
                        $post_params = $parameters;
                        $placeholders = $this->extract_placeholders($post_params);

                        foreach ($placeholders as $placeholder) {
                            $value = $this->get_placeholder_value($placeholder, $item_data);
                            $this->replace_placeholder($post_params, $placeholder, $value);
                            $log[] = "Mapped placeholder '$placeholder' to '$value' for item $index";
                        }

                        // Validate post parameters
                        $title = trim($post_params['post_title'] ?? '');
                        $content = trim($post_params['post_content'] ?? '');
                        if (strlen($title) === 0 || strlen($content) === 0) {
                            $log[] = "Skipping post creation: Empty title or content at item $index (title: '$title', content: '$content')";
                            continue;
                        }

                        // Check for duplicate post
                        $existing_post = get_page_by_title($title, OBJECT, $post_params['post_type'] ?? 'post');
                        if ($existing_post) {
                            $log[] = "Skipping post creation: Post already exists with title '$title' (ID: {$existing_post->ID})";
                            $post_ids[] = $existing_post->ID;
                            continue;
                        }

                        $log[] = "Post parameters for item $index: " . json_encode($post_params);

                        $post_id = wp_insert_post($post_params, true);
                        if (is_wp_error($post_id)) {
                            $log[] = "Error creating post at item $index: " . $post_id->get_error_message();
                            continue;
                        }
                        if ($post_id === 0) {
                            $log[] = "Failed to create post at item $index: wp_insert_post returned 0";
                            continue;
                        }

                        $post_ids[] = $post_id;
                        $log[] = "Post created: ID $post_id";
                    }

                    if (empty($post_ids)) {
                        $log[] = 'No posts created';
                        return ['success' => false, 'message' => 'No posts created'];
                    }

                    return ['success' => true, 'data' => $post_ids];
                case 'wp_set_post_terms':
                    $result = wp_set_post_terms(...array_merge($parameters, $input_data));
                    if (is_wp_error($result)) {
                        throw new Exception($result->get_error_message());
                    }
                    $log[] = "Terms set successfully";
                    return ['success' => true, 'data' => $result];
                case 'update_post_meta':
                    $result = update_post_meta(...array_merge($parameters, $input_data));
                    $log[] = "Post meta updated successfully";
                    return ['success' => true, 'data' => $result];
                default:
                    throw new Exception('Unsupported WordPress function');
            }
        } catch (Exception $e) {
            $log[] = "Error executing WP function $function_name: " . $e->getMessage();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function extract_placeholders($params) {
        $placeholders = [];
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                preg_match_all('/{{(.+?)}}/', $value, $matches);
                $placeholders = array_merge($placeholders, $matches[1]);
            }
        }
        return array_unique($placeholders);
    }

    private function get_placeholder_value($placeholder, $data) {
        $path = explode('.', $placeholder);
        $current = $data;
        foreach ($path as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } else {
                return '';
            }
        }
        return is_scalar($current) ? $current : '';
    }

    private function replace_placeholder(&$params, $placeholder, $value) {
        foreach ($params as $key => &$param_value) {
            if (is_string($param_value)) {
                $param_value = str_replace('{{' . $placeholder . '}}', $value, $param_value);
            }
        }
    }

    private function execute_wc_function($config, $input_data, &$log) {
        if (!class_exists('WooCommerce')) {
            $log[] = 'WooCommerce is not active';
            return ['success' => false, 'message' => 'WooCommerce not active'];
        }

        $function_name = $config['function_name'];
        $parameters = (is_array($config['parameters']) ? $config['parameters'] : (json_decode($config['parameters'], true) ?? []));

        try {
            switch ($function_name) {
                case 'wc_create_product':
                    $product = new WC_Product_Simple();
                    foreach (array_merge($parameters, $input_data) as $key => $value) {
                        $method = 'set_' . $key;
                        if (method_exists($product, $method)) {
                            $product->$method($value);
                        }
                    }
                    $product_id = $product->save();
                    $log[] = "Product created: ID $product_id";
                    return ['success' => true, 'data' => $product_id];
                case 'wc_update_product':
                    $product = wc_get_product($parameters['id'] ?? $input_data['id']);
                    if (!$product) {
                        throw new Exception('Product not found');
                    }
                    foreach (array_merge($parameters, $input_data) as $key => $value) {
                        $method = 'set_' . $key;
                        if (method_exists($product, $method)) {
                            $product->$method($value);
                        }
                    }
                    $product_id = $product->save();
                    $log[] = "Product updated: ID $product_id";
                    return ['success' => true, 'data' => $product_id];
                default:
                    throw new Exception('Unsupported WooCommerce function');
            }
        } catch (Exception $e) {
            $log[] = "Error executing WC function $function_name: " . $e->getMessage();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function parse_html($body, $selectors, &$log) {
        if (!class_exists('DOMDocument')) {
            $log[] = 'DOMDocument class not found';
            return [];
        }

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $body); // Ensure UTF-8 encoding
        $xpath = new DOMXPath($dom);
        $items = [];

        foreach ($selectors as $field => $selector) {
            $nodes = $xpath->query($selector);
            $log[] = "Found " . $nodes->length . " nodes for selector '$selector'";
            foreach ($nodes as $index => $node) {
                $raw_text = $node->nodeValue;
                $text = trim($raw_text);
                $log[] = "Raw text for node $index (selector '$selector'): '$raw_text'";
                if (strlen($text) > 0) {
                    $items[$index][$field] = $text;
                } else {
                    $log[] = "Skipping empty text for node $index (selector '$selector')";
                }
            }
        }

        if (empty($items)) {
            $log[] = 'No valid data extracted with selectors: ' . json_encode($selectors);
        }

        return array_values($items); // Ensure consistent array indexing
    }

    private function parse_xml($body, $selectors, &$log) {
        if (!function_exists('simplexml_load_string')) {
            $log[] = 'SimpleXML not available';
            return [];
        }

        $xml = simplexml_load_string($body);
        if ($xml === false) {
            $log[] = 'Invalid XML';
            return [];
        }

        $items = [];
        foreach ($selectors as $field => $path) {
            $nodes = $xml->xpath($path);
            foreach ($nodes as $index => $node) {
                $items[$index][$field] = (string)$node;
            }
        }

        return array_values($items);
    }

    private function parse_json($body, $json_paths, &$log) {
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $log[] = 'Invalid JSON: ' . json_last_error_msg();
            return [];
        }

        $items = [];
        foreach ($json_paths as $field => $path) {
            $parts = explode('.', $path);
            $current = $data;

            foreach ($parts as $part) {
                if (preg_match('/\[(\d+)\]/', $part, $matches)) {
                    $index = $matches[1];
                    $part = str_replace($matches[0], '', $part);
                    $current = $current[$part][$index] ?? null;
                } else {
                    $current = $current[$part] ?? null;
                }

                if ($current === null) {
                    break;
                }
            }

            if ($current !== null) {
                if (is_array($current)) {
                    foreach ($current as $index => $value) {
                        $items[$index][$field] = $value;
                    }
                } else {
                    $items[0][$field] = $current;
                }
            }
        }

        return array_values($items);
    }

    private function save_log($pipeline_id, $log) {
        $log_file = WP_CONTENT_DIR . '/wwp_logs/pipeline_' . $pipeline_id . '.log';
        $log_entry = date('Y-m-d H:i:s') . "\n" . implode("\n", $log) . "\n\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
}

new WordPress_Workflow_Pipeline();
?>