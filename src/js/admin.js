/**
 * Plugin Template admin js.
 *
 *  @package WordPress Plugin Template/JS
 */


jQuery( document ).ready(
	function ( $ ) {

		/***** Date Time Picker translation */
		$.timepicker.regional['pt-BR'] = {

			monthNames: ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'],
			monthNamesShort: ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'],
			dayNames: ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sabado'],
			dayNamesShort: ['Dom','Seg','Ter','Qua','Qui','Sex','Sab'],
			dayNamesMin: ['Do','Se','Te','Qua','Qui','Se','Sa'],
			weekHeader: 'Sem',
			timeOnlyTitle: 'Escolha a hora',
			timeText: 'Hora',
			hourText: 'Horas',
			minuteText: 'Minutos',
			secondText: 'Segundos',
			millisecText: 'Milissegundos',
			timezoneText: 'Fuso horário',
			currentText: 'Agora',
			closeText: 'Fechar',
			timeFormat: 'HH:mm',
			dateFormat: 'dd/mm/yy',
			amNames: ['a.m.', 'AM', 'A'],
			pmNames: ['p.m.', 'PM', 'P'],
			ampm: false
		}
		$.timepicker.setDefaults($.timepicker.regional['pt-BR'])



		jQuery('#eitagcr_enable_delivery_place, #eitagcr_enable_simplified_dashboard').change(() => {
			var is_menu_tab_settings = jQuery('.redux-group-menu .sementes-settings.active');
			if (is_menu_tab_settings.length) {
				var is_first_time = true;
				var e = jQuery('#redux-footer .spinner')[0];
				var observer = new MutationObserver(function (event) {
					if (!is_first_time) {
						window.onbeforeunload = null;
						window.location.reload();
					}
					is_first_time = false;
				})

				observer.observe(e, {
					attributes: true,
					attributeFilter: ['class'],
					childList: false,
					characterData: false
				})

				jQuery('#redux-footer #redux_bottom_save').click();
			}
		});



		/***** Set DateTimePicker *****/
		$('#cycle_open_time').datetimepicker();
		$('#cycle_close_time').datetimepicker();

		function ajax_get_report_or_analytics(action, form_id) {
			var form_data = jQuery('form.redux-form-wrapper').serializeArray();
			form_data.push({name: 'form_id', value: form_id});
			form_data = jQuery.param(form_data);
			var data = {
				action: action,
				security: CYCLE_AJAX_VARS.security_get_cycle_report,
				form_data: form_data
			};
			jQuery.post(ajaxurl, data, function(response) {
				jQuery('input#redux_bottom_save').click();
				response = JSON.parse(response.slice(0, -1));
				$(response.html_element + " #sementes_analytics_1").DataTable().clear().destroy();
				$(response.html_element + " #sementes_analytics_2").DataTable().clear().destroy();
				$(response.html_element + " #sementes_analytics_3").DataTable().clear().destroy();
				
				jQuery(response.html_element)[0].innerHTML = response.html_content;
				
				$(response.html_element + " #sementes_analytics_1").DataTable(datatable_config);
				$(response.html_element + " #sementes_analytics_2").DataTable(datatable_config);
				$(response.html_element + " #sementes_analytics_3").DataTable(datatable_config);
			});
		}

		var ajax_action = 'get_cycle_report';
		jQuery('select#cycle_report_1-select').change(() => ajax_get_report_or_analytics('get_cycle_report', 1)); 
		jQuery('select#cycle_report_2-select').change(() => ajax_get_report_or_analytics('get_cycle_report', 2)); 
		jQuery('select#cycle_report_3-select').change(() => ajax_get_report_or_analytics('get_cycle_report', 3)); 
		jQuery('select#cycle_report_4-select').change(() => ajax_get_report_or_analytics('get_cycle_report', 4)); 
		jQuery('select#cycle_report_5-select').change(() => ajax_get_report_or_analytics('get_cycle_report', 5)); 
		jQuery('select#cycle_report_6-select').change(() => ajax_get_report_or_analytics('get_cycle_report', 6));
		jQuery('select#cycle_report_7-select').change(() => ajax_get_report_or_analytics('get_cycle_report', 7)); 
		jQuery('select#cycle_report_8-select').change(() => ajax_get_report_or_analytics('get_cycle_report', 8));
		jQuery('select#cycle_report_9-select').change(() => ajax_get_report_or_analytics('get_cycle_report', 9)); 

		jQuery('select#orderby_report_1-select').change(() => ajax_get_report_or_analytics('get_cycle_report', 1)); 
		jQuery('input#eitagcr_include_client_info_report_1').change(() => ajax_get_report_or_analytics('get_cycle_report', 1)); 
		jQuery('input#eitagcr_include_client_info_report_4').change(() => ajax_get_report_or_analytics('get_cycle_report', 4)); 
		jQuery('input#eitagcr_remove_local_pickup_report_7').change(() => ajax_get_report_or_analytics('get_cycle_report', 7)); 
		
		jQuery('input#eitagcr_includetax_report_1').change(() => ajax_get_report_or_analytics('get_cycle_report', 1)); 
		jQuery('input#eitagcr_includetax_report_2').change(() => ajax_get_report_or_analytics('get_cycle_report', 2)); 
		jQuery('input#eitagcr_includetax_report_3').change(() => ajax_get_report_or_analytics('get_cycle_report', 3)); 
		jQuery('input#eitagcr_includetax_report_4').change(() => ajax_get_report_or_analytics('get_cycle_report', 4)); 
		jQuery('input#eitagcr_includetax_report_5').change(() => ajax_get_report_or_analytics('get_cycle_report', 5)); 
		jQuery('input#eitagcr_includetax_report_6').change(() => ajax_get_report_or_analytics('get_cycle_report', 6));
		jQuery('input#eitagcr_includetax_report_7').change(() => ajax_get_report_or_analytics('get_cycle_report', 7));
		jQuery('input#eitagcr_includetax_report_8').change(() => ajax_get_report_or_analytics('get_cycle_report', 8));
		jQuery('input#eitagcr_includetax_report_9').change(() => ajax_get_report_or_analytics('get_cycle_report', 9));  

		jQuery('select#cycle_analytics_1-select').change(() => ajax_get_report_or_analytics('get_cycle_analytics', 1)); 
		jQuery('select#cycle_analytics_2-select').change(() => ajax_get_report_or_analytics('get_cycle_analytics', 2));
		jQuery('select#cycle_analytics_3-select').change(() => ajax_get_report_or_analytics('get_cycle_analytics', 2));
		



		/***** Validate cycle via ajax before post publish *****/
		var is_valid_cycle = false;
		jQuery('#publish').click(function(e) {
			if (jQuery('#redux-eitagcr-metabox-metabox-cycle-dates').length ) {
				if (is_valid_cycle) {
					is_valid_cycle = false;
					return;
				}

				e.preventDefault();
				var form_data = jQuery('#post').serializeArray();
				form_data = jQuery.param(form_data);
				var data = {
					action: 'cycle_pre_submit_validation',
					security: CYCLE_AJAX_VARS.security_pre_cycle_publish_validation,
					form_data: form_data
				};
				jQuery.post(ajaxurl, data, function(response) {
					if (response.indexOf('True') > -1 || response.indexOf('true') > -1 || response === true ) {
						is_valid_cycle = true;
						jQuery('#publish').click();
					} else {
						window.onbeforeunload = null;
						alert(response.slice(0, -1));
					}
				});
			}
		});

		var is_download_btn = false;
		var download_btns = jQuery("button[name=report_data]");
		if (download_btns.length > 0) {
			for (let i = 0; i < download_btns.length; i++) {
				download_btns[i].addEventListener("click", function() {
					is_download_btn = true;
				});
			}
		}

		/** Clean report select  */
		var reports_default_cycle = jQuery("input[is_default_cycle_id=true]");
		if (reports_default_cycle.length > 0) {
			window.addEventListener("beforeunload", function(event) {
				if (!is_download_btn) {
					var reports_default_cycle = jQuery("input[is_default_cycle_id=true]");
					if (reports_default_cycle.length > 0) {
						reports_default_cycle.each( function(i) {
							var report_id = reports_default_cycle[i].attributes["report_id"].value;
							var value = reports_default_cycle[i].value;
							var report_select = jQuery("#cycle_report_"+report_id+"-select");
							report_select[0].value = value;
						})
					}
				} else {
					is_download_btn = false;
				}
			});
		}

		/** Init analytics datables  */
		var datatable_config = {
			paging: false,
			dom: 'Bfrtip',
			buttons: [
				'csvHtml5',
            	'pdfHtml5'
			],
			responsive: false,
			columnDefs: [
				{
					targets: -1,
					className: 'dt-body-right'
				}
			],
			language: {
				processing:     "Processando...",
				search:         "",
				searchPlaceholder:  "Digite para buscar...",
				lengthMenu:     "Mostrar _MENU_ entradas",
				info:           "Exibindo entradas de _START_ a _END_ de um total de _TOTAL_ entradas",
				infoEmpty:      "Exibindo 0 de 0 entradas.",
				infoFiltered:   "(filtradas de _MAX_ entradas no total)",
				infoPostFix:    "",
				loadingRecords: "Carregando entradas...",
				zeroRecords:    "Nenhuma entrada para exibir",
				emptyTable:     "Nenhum dado para exibir na tabela",
				paginate: {
					first:      "Primeiro",
					previous:   "Anterior",
					next:       "Próximo",
					last:       "Útimo"
				},
				aria: {
					sortAscending:  ": clique para ordenar de forma crescente",
					sortDescending: ": clique para ordenar de forma decrescente"
				}
		},
		}
		$("#sementes_analytics_1").DataTable(datatable_config);
		$("#sementes_analytics_2").DataTable(datatable_config);		
		$("#sementes_analytics_3").DataTable(datatable_config);			
		
	}  				
);
