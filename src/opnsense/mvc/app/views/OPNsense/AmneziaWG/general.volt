{#
 # Copyright (c) 2024 AmneziaWG Plugin
 # All rights reserved.
 #}

<script>
    $( document ).ready(function() {
        // Load general settings directly
        ajaxGet("/api/amneziawg/general/get", {}, function(data, status){
            if (data.general && data.general.enabled !== undefined) {
                $("#enabled").prop('checked', data.general.enabled == '1');
            }
        });

        $("#{{formGridInstance['table_id']}}").UIBootgrid({
            search: '/api/amneziawg/instance/search_instance',
            get: '/api/amneziawg/instance/get_instance/',
            set: '/api/amneziawg/instance/set_instance/',
            add: '/api/amneziawg/instance/add_instance/',
            del: '/api/amneziawg/instance/del_instance/',
            toggle: '/api/amneziawg/instance/toggle_instance/'
        });

        $("#reconfigureAct").SimpleActionButton({
            onPreAction: function() {
                const dfObj = new $.Deferred();
                saveFormToEndpoint("/api/amneziawg/general/set", 'frm_general_settings', function(){
                    dfObj.resolve();
                });
                return dfObj;
            }
        });

        /**
         * Move keypair generation button inside the instance form and hook api event
         */
        $("#control_label_instance\\.private_key").append($("#keygen_div").detach().show());
        $("#keygen").click(function(){
            ajaxGet("/api/amneziawg/instance/key_pair", {}, function(data, status){
                if (data.status && data.status === 'ok') {
                    $("#instance\\.private_key").val(data.privkey);
                }
            });
        });

        // update history on tab state and implement navigation
        if(window.location.hash != "") {
            $('a[href="' + window.location.hash + '"]').click()
        }
        $('.nav-tabs a').on('shown.bs.tab', function (e) {
            history.pushState(null, null, e.target.hash);
        });
        $(window).on('hashchange', function(e) {
            $('a[href="' + window.location.hash + '"]').click()
        });
        
        // Handle import data from localStorage
        $(document).ready(function() {
            var importData = localStorage.getItem('amneziawg_import_data');
            if (importData) {
                try {
                    var data = JSON.parse(importData);
                    
                    // Wait a bit for the page to load, then open the add dialog
                    setTimeout(function() {
                        // Try different selectors for the add button
                        var addButton = $("#{{formGridInstance['table_id']}}-header .actionBar .actions .btn-add");
                        
                        if (addButton.length === 0) {
                            // Try alternative selectors
                            addButton = $(".btn-add");
                        }
                        
                        if (addButton.length === 0) {
                            // Try more specific selector
                            addButton = $("[data-action='add']");
                        }
                        
                        if (addButton.length > 0) {
                            addButton.click();
                            
                            // Wait for dialog to be ready and fill it
                            setTimeout(function() {
                                // Fill simple fields that work reliably
                                if (data.name) $("#instance\\.name").val(data.name);
                                if (data.description) $("#instance\\.description").val(data.description);
                                if (data.private_key) $("#instance\\.private_key").val(data.private_key);
                                if (data.listen_port) $("#instance\\.listen_port").val(data.listen_port);
                                if (data.table) $("#instance\\.table").val(data.table);
                                if (data.postup) $("#instance\\.postup").val(data.postup);
                                if (data.preup) $("#instance\\.preup").val(data.preup);
                                if (data.mtu) $("#instance\\.mtu").val(data.mtu);
                                if (data.jc) $("#instance\\.jc").val(data.jc);
                                if (data.jmin) $("#instance\\.jmin").val(data.jmin);
                                if (data.jmax) $("#instance\\.jmax").val(data.jmax);
                                if (data.s1) $("#instance\\.s1").val(data.s1);
                                if (data.s2) $("#instance\\.s2").val(data.s2);
                                if (data.s3) $("#instance\\.s3").val(data.s3);
                                if (data.s4) $("#instance\\.s4").val(data.s4);
                                if (data.h1) $("#instance\\.h1").val(data.h1);
                                if (data.h2) $("#instance\\.h2").val(data.h2);
                                if (data.h3) $("#instance\\.h3").val(data.h3);
                                if (data.h4) $("#instance\\.h4").val(data.h4);
                                if (data.i1) $("#instance\\.i1").val(data.i1);
                                if (data.peer_public_key) $("#instance\\.peer_public_key").val(data.peer_public_key);
                                if (data.peer_preshared_key) $("#instance\\.peer_preshared_key").val(data.peer_preshared_key);
                                if (data.peer_endpoint) $("#instance\\.peer_endpoint").val(data.peer_endpoint);
                                if (data.peer_persistent_keepalive) $("#instance\\.peer_persistent_keepalive").val(data.peer_persistent_keepalive);
                                if (data.peer_routes) $("#instance\\.peer_routes").val(data.peer_routes);
                                
                                // Handle select_multiple fields by switching to text mode
                                if (data.address) {
                                    var textButton = $("#to-text_instance\\.address");
                                    if (textButton.length > 0) {
                                        textButton.click();
                                        setTimeout(function() {
                                            var textarea = $("#textarea_instance\\.address textarea");
                                            if (textarea.length > 0) {
                                                textarea.val(data.address);
                                                textarea.trigger('input');
                                                textarea.trigger('change');
                                                setTimeout(function() {
                                                    $("#to-select_instance\\.address").click();
                                                }, 500);
                                            }
                                        }, 1000);
                                    }
                                }
                                
                                if (data.dns) {
                                    var textButton = $("#to-text_instance\\.dns");
                                    if (textButton.length > 0) {
                                        textButton.click();
                                        setTimeout(function() {
                                            var textarea = $("#textarea_instance\\.dns textarea");
                                            if (textarea.length > 0) {
                                                textarea.val(data.dns);
                                                textarea.trigger('input');
                                                textarea.trigger('change');
                                                setTimeout(function() {
                                                    $("#to-select_instance\\.dns").click();
                                                }, 600);
                                            }
                                        }, 1200);
                                    }
                                }
                                
                                if (data.peer_allowed_ips) {
                                    var textButton = $("#to-text_instance\\.peer_allowed_ips");
                                    if (textButton.length > 0) {
                                        textButton.click();
                                        setTimeout(function() {
                                            var textarea = $("#textarea_instance\\.peer_allowed_ips textarea");
                                            if (textarea.length > 0) {
                                                textarea.val(data.peer_allowed_ips);
                                                textarea.trigger('input');
                                                textarea.trigger('change');
                                                setTimeout(function() {
                                                    $("#to-select_instance\\.peer_allowed_ips").click();
                                                }, 700);
                                            }
                                        }, 1400);
                                    }
                                }
                                
                                // Clear the data from localStorage
                                localStorage.removeItem('amneziawg_import_data');
                            }, 1000);
                        } else {
                            localStorage.removeItem('amneziawg_import_data');
                        }
                    }, 2000);
                } catch (e) {
                    localStorage.removeItem('amneziawg_import_data');
                }
            }
        });
    });
</script>

<!-- Navigation bar -->
<ul class="nav nav-tabs" data-tabs="tabs" id="maintabs">
    <li class="active"><a data-toggle="tab" id="tab_instances" href="#instances">{{ lang._('Instances') }}</a></li>
</ul>

<div class="tab-content content-box tab-content">
    <div id="instances" class="tab-pane fade in active">
        <span id="keygen_div" style="display:none" class="pull-right">
            <button id="keygen" type="button" class="btn btn-secondary" title="{{ lang._('Generate new keypair.') }}" data-toggle="tooltip">
              <i class="fa fa-fw fa-gear"></i>
            </button>
        </span>
        {{ partial('layout_partials/base_bootgrid_table', formGridInstance)}}
    </div>
    {{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings'])}}
</div>
{{ partial('layout_partials/base_apply_button', {'data_endpoint': '/api/amneziawg/service/reconfigure'}) }}
{{ partial("layout_partials/base_dialog",['fields':formDialogEditInstance,'id':formGridInstance['edit_dialog_id'],'label':lang._('Edit instance')])}} 