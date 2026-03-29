{#
 # Copyright (c) 2024 AmneziaWG Plugin
 # All rights reserved.
 #}

<script>
    $( document ).ready(function() {
        var importForm = null;
        
        // Import functionality
        $("#import_config").click(function() {
            var configText = $("#config_text").val();
            if (!configText.trim()) {
                alert("{{ lang._('Please enter configuration text or select a file.') }}");
                return;
            }
            
            $.ajax({
                url: "/api/amneziawg/import/parse",
                type: "POST",
                data: { config: configText },
                success: function(response) {
                    if (response.status === 'ok') {
                        // Store data in localStorage and redirect to instances page
                        localStorage.setItem('amneziawg_import_data', JSON.stringify(response.data));
                        window.location.href = "/ui/amneziawg/general#instances";
                    } else {
                        alert("{{ lang._('Error parsing configuration:') }} " + response.message);
                    }
                },
                error: function() {
                    alert("{{ lang._('Error parsing configuration.') }}");
                }
            });
        });
        
        // Handle file selection
        $("#config_file").change(function() {
            var file = this.files[0];
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $("#config_text").val(e.target.result);
                };
                reader.readAsText(file);
            }
        });
        
        // Handle clear button
        $("#clear_config").click(function() {
            $("#config_text").val('');
            $("#config_file").val('');
        });
    });
</script>

<div class="container-fluid">
    <!-- Import Section -->
    <div id="import_section">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">{{ lang._('Import AmneziaWG Configuration') }}</h3>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="config_text">{{ lang._('Configuration') }}</label>
                                    <textarea id="config_text" class="form-control" rows="20" placeholder="[Interface]&#10;PrivateKey = ...&#10;Address = ...&#10;&#10;[Peer]&#10;PublicKey = ...&#10;Endpoint = ..."></textarea>
                                    <small class="help-block">{{ lang._('Paste AmneziaWG configuration here or select a file below.') }}</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="config_file">{{ lang._('File') }}</label>
                                    <input type="file" id="config_file" class="form-control" accept=".conf">
                                    <small class="help-block">{{ lang._('Select AmneziaWG configuration file (.conf)') }}</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="btn-group">
                                    <button type="button" class="btn btn-primary" id="import_config">
                                        <i class="fa fa-fw fa-upload"></i> {{ lang._('Import') }}
                                    </button>
                                    <button type="button" class="btn btn-default" id="clear_config">
                                        <i class="fa fa-fw fa-trash"></i> {{ lang._('Clear') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row" style="margin-top: 20px;">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <h4>{{ lang._('Supported Configuration Format') }}</h4>
                                    <p>{{ lang._('The import supports standard AmneziaWG configuration files with the following sections:') }}</p>
                                    <ul>
                                        <li><strong>[Interface]</strong> - Interface settings (PrivateKey, Address, DNS, etc.)</li>
                                        <li><strong>[Peer]</strong> - Peer settings (PublicKey, Endpoint, AllowedIPs, etc.)</li>
                                    </ul>
                                    <p>{{ lang._('AmneziaWG 2.0 [Interface] parameters: Jc, Jmin, Jmax, S1, S2, S3, S4, H1, H2, H3, H4, and optional I1 (CPS). AWG 1.x UserLand lines are ignored.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>