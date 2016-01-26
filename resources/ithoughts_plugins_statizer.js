$(function(){
	String.prototype.format = function() {
		var formatted = this;
		for (var i = 0; i < arguments.length; i++) {
			var regexp = new RegExp('\\{'+i+'\\}', 'gi');
			formatted = formatted.replace(regexp, arguments[i]);
		}
		return formatted;
	};

	Highcharts.setOptions({
		lang: ithoughts_plugins_statizer.lang.highcharts
	});
	console.log(ithoughts_plugins_statizer_plugins);
	$('[id^="plugins_data-"][data-ajaxed="true"]').each(function(){
		var plugins = decodeJSONAttr(this.getAttribute("data-plugins"));
		var details = decodeJSONAttr(this.getAttribute("data-details"));
		var chartId = this.id;
		var maxDays = this.getAttribute("data-maxDays");
		var theme = this.getAttribute("data-theme");
		var data = {
			plugins: plugins,
			details: details,
			chartId: chartId
		};
		if(parseInt(maxDays) > 0)
			data["maxDays"] = parseInt(maxDays);
		if(theme && theme.length > 0)
			data["theme"] = theme;
		$.post(ithoughts_plugins_statizer.ajax,{
			action: "ithoughts_plugins_statizer-get_chart_ajax", data: data
		}, function(out){
			if(out.success){
				ithoughts_plugins_statizer_plugins.push(out.data);
				initCharts();
			} else {
				console.error("Error while getting chart via Ajax", out);
			}
		});
	});




	function decodeJSONAttr(str){
		return JSON.parse(window.decodeURIComponent(str));
	}
	function generateId(string, type){
		return window.encodeURIComponent(string) + "___" + type
	}
	function roundDateToDay(date){
		date.setHours(0);
		date.setMinutes(0);
		date.setSeconds(0);
		date.setMilliseconds(0);
		return date;
	}
	function hexToRgb(hex) {
		var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
		return result ? {
			r: parseInt(result[1], 16),
			g: parseInt(result[2], 16),
			b: parseInt(result[3], 16)
		} : null;
	}
	function setFillColorHover(data){
		if(!data.options.fillColor) return;
		//console.log("Set hover manually", data, data.options.fillColor);
		// Set hover manually
		var color = data.options.fillColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+),\s*(\d*.?\d*)\)/);
		color = {
			r: parseInt(color[1]),
			g: parseInt(color[2]),
			b: parseInt(color[3]),
			a: parseFloat(color[4]),
		};
		//console.log(data);
		var interval = setInterval(function(){
			//console.log("Set colors",data.pointAttr.hover.fill, 'rgb(' + color.r + ',' + color.g + ',' + color.b + ')')
			if(data.pointAttr.hover.fill == 'rgb(' + color.r + ',' + color.g + ',' + color.b + ')'){
				clearInterval(interval);
				return;
			} else {
				data.pointAttr.hover.fill = 'rgb(' + color.r + ',' + color.g + ',' + color.b + ')';
				data.data.forEach(function(elem){
					//console.log("Setting point", elem, color)
					elem.pointAttr.hover.fill = 'rgb(' + color.r + ',' + color.g + ',' + color.b + ')';
				});
			}
		},10);
	}
	var minDate;

	function initCharts(){
		for(var i = 0, j = ithoughts_plugins_statizer_plugins.length; i < j; i++){
			var chart = ithoughts_plugins_statizer_plugins[i];
			if(chart.inited == true)
				continue;
			chart.inited = true;

			var minDate = null;
			var maxDays = chart.maxDays;
			var nowTimestamp = (new Date()).getTime();
			var dtt = 1000 * 60 * 60 * 24;
			for(var plugin in chart.plugins){
				var pluginCreation = (new Date(chart.plugins[plugin].creationDate)).getTime();
				if(isNaN(pluginCreation))
					pluginCreation = nowTimestamp;
				var downloadsLast = nowTimestamp;
				if(chart.plugins[plugin].downloads)
					downloadsLast = nowTimestamp - (Object.keys(chart.plugins[plugin].downloads).length * dtt);
				var activeLast = nowTimestamp;
				if(chart.plugins[plugin].active)
					activeLast = nowTimestamp - (Object.keys(chart.plugins[plugin].active).length * dtt);
				var dateTimed = Math.max(Math.min(pluginCreation, downloadsLast, activeLast), nowTimestamp - (maxDays * dtt));
				dateTimed = roundDateToDay(new Date(dateTimed));
				if(minDate == null || minDate > dateTimed)
					minDate = dateTimed;
			}

			$container = $('#' + chart.chartId);
			var chartOptions = {
				chart: {
					type: 'spline',
					alignTicks: false,
					events: {
						redraw: function(){
							for(var k = 0, l = this.series.length; k < l; k++)
								setFillColorHover(this.series[k]);
						}
					}
				},
				title: {
					text: $container[0].getAttribute("data-title")
				},
				legend: {
					enabled: false
				},
				xAxis: [
					{
						type: "datetime",
						title: {
							text: ithoughts_plugins_statizer.lang.labels["xAxisDate"]
						},
						plotLines: [
						],
						min: roundDateToDay(minDate).getTime(),
						dateTimeLabelFormats: {
							millisecond: 'millisecond %H:%M:%S.%L',
							second: 'second %H:%M:%S',
							minute: 'minute %H:%M',
							hour: 'hour %H:%M',
							day: 'day %e. %b',
							week: ithoughts_plugins_statizer.lang.dateformat.week,
							month: ithoughts_plugins_statizer.lang.dateformat.month,
							year: 'year %Y'
						}
					},
					{
						type: "datetime",
						title: {
							text: ithoughts_plugins_statizer.lang.labels["xAxisDate"]
						},
						plotLines: [
						],
						opposite: true,
						visible: false,
						min: roundDateToDay(minDate).getTime()
					}
				],
				plotOptions: {
					spline: {
						marker: {
							enabled: false
						},
					}
				},
				tooltip: {
					formatter: function() {
						if(this.point.text)
							return '<span style="font-size:0.75em">' + Highcharts.dateFormat(ithoughts_plugins_statizer.lang.dateformat.full, new Date(this.x)) + "</span><br/>" + this.point.text;
						return  '<span style="font-size:0.75em">' + Highcharts.dateFormat(ithoughts_plugins_statizer.lang.dateformat.full, new Date(this.x)) + "</span><br/>" + this.series.name + ':' + this.y;
					}
				},
				/*tooltip: {
				formatter: function () {
					console.log(this);
					var str = '<span style="font-size:0.75em;">' + Highcharts.dateFormat("%a, %b %e, %Y", this.point.x) + "</span><br />"
					if(this.point.version)
						return str + "<b>" + this.series.name + "</b>: v" + this.point.version;
					if(this.point.downloads)
						return str + "<b>" + this.series.name + "</b>: More than " + this.point.downloads + " downloads";
					if(this.point.active)
						return str + "<b>" + this.series.name + "</b>: More than " + this.point.active + " active installs";

					return str + "<b>" + this.series.name + "</b>: " + this.point.y;
				}
			},*/
				yAxis: [
					{
						title: {
							text: ithoughts_plugins_statizer.lang.labels["yAxisDownloads"]
						},
						min: 0,
						gridLineColor: chart.colors.axis[0]
					},
					{
						title: {
							text: ithoughts_plugins_statizer.lang.labels["yAxisActive"]
						},
						opposite: true,
						type: 'logarithmic',
						allowDecimals: false,
						tickInterval: 1,
						min: 1,
						gridLineColor: chart.colors.axis[1]
					}
				],
				series: []
			};
			for(var plugin in chart.plugins){
				var name;
				if(chart.plugins[plugin].name)
					name = chart.plugins[plugin].name;
				else
					name = plugin;
				var pluginIndex = Object.keys(chart.plugins).indexOf(plugin);





				if(!chart["seriesTable"]){
					chart["seriesTable"] = {};
				}
				if(!chart["seriesTable"][window.encodeURIComponent(name)]){
					chart["seriesTable"][window.encodeURIComponent(name)] = {};
				}






				// Series
				{
					var dataArray = [];
					var zones = [];/*
					if(chart.plugins[plugin].downloadsToday){
						zones.push({
							value: 0,
							color: "#000000"
						});
					}*/
					for(var o in chart.plugins[plugin].downloads) {
						dataArray.push(chart.plugins[plugin].downloads[o]);
					}
					var keys = Object.keys(chart.plugins[plugin].downloads)
					zones.push({
						value:roundDateToDay(new Date(keys[keys.length - 1])).getTime() - dtt,
						dashStyle: "solid"
					});
					if(typeof chart.plugins[plugin].downloadsToday != "undefined"){
						zones.push({
							value: roundDateToDay(new Date()).getTime() + dtt,
							dashStyle: "shortdot"
						});
						dataArray.push(chart.plugins[plugin].downloadsToday);
					}
					var serieId = generateId(name, "downloads");
					var serieDl = {
						type: "spline",
						yAxis: 0,
						name: ithoughts_plugins_statizer.lang.labels["downloadSerie"].format(name),
						data: dataArray,
						pointStart: roundDateToDay(new Date()).getTime() - ((dataArray.length - (chart.plugins[plugin].downloadsToday ? 1 : 0)) * dtt),
						pointInterval: dtt,
						id: serieId,
						color: "#" + chart.colors.series[pluginIndex][0],
					};
					if(typeof chart.plugins[plugin].downloadsToday != "undefined"){
						serieDl = $.extend(serieDl, {
							zoneAxis: 'x',
							zones: zones
						});
					}
					chartOptions["series"].push(serieDl);
					chart["seriesTable"][window.encodeURIComponent(name)]["downloads"] = {
						id: serieId,
						name: name
					};
					/*if(chart.plugins[plugin].downloadsToday){
						var obj = {};
						obj[roundDateToDay(new Date()).getTime()] = chart.plugins[plugin].downloadsToday;
						console.log(obj);
						chartOptions["series"].push({
							type: "spline",
							yAxis: 0,
							name: ithoughts_plugins_statizer.lang.labels["downloadSerie"].format(name),
							data: [{
								x: roundDateToDay(new Date()).getTime() - 100000000,
								y: chart.plugins[plugin].downloadsToday
							},{
								x: roundDateToDay(new Date()).getTime(),
								y: chart.plugins[plugin].downloadsToday
							}],
							color: "#" + chart.colors.series[pluginIndex][0],
							linkedTo: serieId
						});
					}*/


					var dataArray = [];
					for(var o in chart.plugins[plugin].active) {
						dataArray.push(chart.plugins[plugin].active[o]);
					}
					var serieId = generateId(name, "active");
					chartOptions["series"].push({
						type: "line",
						yAxis: 1,
						name: ithoughts_plugins_statizer.lang.labels["activeSerie"].format(name),
						step: "center",
						data: dataArray,
						pointStart: roundDateToDay(new Date()).getTime() - (dataArray.length * dtt),
						pointInterval: dtt,
						id: serieId,
						color: "#" + chart.colors.series[pluginIndex][1]
					});
					chart["seriesTable"][window.encodeURIComponent(name)]["active"] = {
						id: serieId,
						name: name
					};
				}








				//Events
				{
					var opacity = 0.5;

					if(chart.plugins[plugin].events){
						if(chart.plugins[plugin].events.versions){
							var color = hexToRgb(chart.colors.series[pluginIndex][2]);
							var versions = {
								type:"flags",
								xAxis: 1,
								fillColor: 'rgba(' + color.r + ',' + color.g + ',' + color.b + ',' + opacity + ')',
								data: [],
								name: name,
								shape: "flag",
								zIndex: 10 + pluginIndex + 2
							}
							for(var date in chart.plugins[plugin].events.versions){
								var version = chart.plugins[plugin].events.versions[date];
								versions.data.push({
									x: roundDateToDay(new Date(date)).getTime(),
									title: "v" + version,
									text: ithoughts_plugins_statizer.lang.labels["eventVersion"].format(version, name)
								});
							}
							chartOptions["series"].push(versions);
						}

						if(chart.plugins[plugin].events.downloads){
							var color = hexToRgb(chart.colors.series[pluginIndex][3]);
							var downloads = {
								yAxis: 0,
								type:"flags",
								onSeries: generateId(name, "downloads"),
								fillColor: 'rgba(' + color.r + ',' + color.g + ',' + color.b + ',' + opacity + ')',
								data: [],
								name: name,
								shape: "circlepin",
								zIndex: 10 + pluginIndex + 0
							}
							for(var date in chart.plugins[plugin].events.downloads){
								var download = chart.plugins[plugin].events.downloads[date];
								downloads.data.push({
									x: roundDateToDay(new Date(date)).getTime(),
									title: download,
									text: ithoughts_plugins_statizer.lang.labels["eventDownloads"].format(download, name)
								});
							}
							chartOptions["series"].push(downloads);
						}

						if(chart.plugins[plugin].events.active){
							var color = hexToRgb(chart.colors.series[pluginIndex][4]);
							var actives = {
								yAxis: 1,
								type:"flags",
								onSeries: generateId(name, "active"),
								fillColor: 'rgba(' + color.r + ',' + color.g + ',' + color.b + ',' + opacity + ')',
								data: [],
								name: name,
								shape: "squarepin",
								zIndex: 10 + pluginIndex + 1
							}
							for(var date in chart.plugins[plugin].events.active){
								var active = chart.plugins[plugin].events.active[date];
								actives.data.push({
									x: roundDateToDay(new Date(date)).getTime(),
									title: active,
									text: ithoughts_plugins_statizer.lang.labels["eventActive"].format(active, name)
								});
							}
							chartOptions["series"].push(actives);
						}
					}
				}
			}



			//console.log(chartOptions);
			$container.find(".chart").highcharts(chartOptions, function (c) {

				chart["chart"] = c;
				var series = [];
				for(var plugin in chart.plugins){
					cplugin = chart.plugins[plugin];
					if(cplugin.active && series.indexOf("active") == -1)
						series.push("active");
					if(cplugin.downloads && series.indexOf("downloads") == -1)
						series.push("downloads");
					if(cplugin.events && Object.keys(cplugin.events).length > 0 && series.indexOf("events") == -1)
						series.push("events");
				}
				var $legend = $container.find(".customLegend");
				var $table = $($.parseHTML("<table></table>"))
				$legend.append($table);
				{
					var seriesHeading = "";
					for(serie in series){
						var text = series[serie];
						if(ithoughts_plugins_statizer.lang.labels[series[serie]])
							text = ithoughts_plugins_statizer.lang.labels[series[serie]];
						seriesHeading += "<th class=\"item-group\" data-direction=\"column\">" + text + "</th>"
					}
					$table.append($.parseHTML("<tr><th></th>" + seriesHeading + "</tr>"));
				}
				{
					var strings = {};
					$.each(c.series, function (j, data) {
						//console.log("Data:",data);


						// Organize for legend
						var serieId = data.userOptions.id;
						if(!serieId){
							if(strings[data.userOptions.name]){
								if(!strings[data.userOptions.name]["events"]){
									strings[data.userOptions.name]["events"] = []
								}
							}
							if(data.options.fillColor){
								var color = data.options.fillColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+),\s*(\d*.?\d*)\)/);
								color = {
									r: parseInt(color[1]),
									g: parseInt(color[2]),
									b: parseInt(color[3]),
									a: parseFloat(color[4]),
								};
								if(strings[data.userOptions.name] && strings[data.userOptions.name]["events"]){
									strings[data.userOptions.name]["events"].push({
										index: j,
										color: 'rgb(' + color.r + ',' + color.g + ',' + color.b + ')'
									});
								}
							} else {
								if(strings[data.userOptions.name] && strings[data.userOptions.name]["events"]){
									strings[data.userOptions.name]["events"].push({
										index: j,
										color: data.color
									});
								}
							}
						} else {
							var serieInfos = serieId.split("___");
							if(!strings[window.decodeURIComponent(serieInfos[0])])
								strings[window.decodeURIComponent(serieInfos[0])] = {};
							strings[window.decodeURIComponent(serieInfos[0])][serieInfos[1]] = {
								index: j,
								color: data.color
							};
						}
						setFillColorHover(data);
					});
					var str = "";
					for(var plugin_name in strings){
						str += "<tr><th class=\"item-group\" data-direction=\"line\">" + plugin_name + "</th>"
						for(var attrIndex in series){
							var attrType = series[attrIndex];
							if(strings[plugin_name][attrType]){
								if(strings[plugin_name][attrType].constructor === Array){
									var indexes = [];
									var divs = [];
									for(var k = 0,l = strings[plugin_name][attrType].length; k < l; k++){
										indexes.push(strings[plugin_name][attrType][k].index);
										divs.push("<div style=\"display:inline-block;background-color:" + strings[plugin_name][attrType][k].color + ";width:25px;height:5px;border-radius:5px;\"></div>");
									}
									str += "<td class=\"item\" data-index=\"" + indexes.join(", ") + "\" style=\"text-align:center\">" + divs.join() + "</td>";
								} else {
									str += "<td class=\"item\" data-index=\"" + strings[plugin_name][attrType].index + "\" style=\"text-align:center\"><div style=\"display:inline-block;background-color:" + strings[plugin_name][attrType].color + ";width:25px;height:5px;border-radius:5px;\"></div></td>";
								}
							} else {
								str += "<td></td>";
							}
						}
						str += "</tr>";
					}
					$table.append($.parseHTML(str));
				}

				$container.find(".customLegend  .item").click(function(){
					var inx = this.getAttribute("data-index");
					var status = this.getAttribute("data-active");
					if(status == null)
						status = true;
					if(status === "false")
						status = false;
					if(status === "true")
						status = true;
					status = !status;
					this.setAttribute("data-active", status);
					var inxs;
					if(inx.indexOf(",") > -1){
						inxs = inx.split(",");
						inxs.forEach(function(elem, index, arr){
							arr[index] = parseInt(elem);
							//return parseInt(elem);
						});
					} else {
						inxs = [parseInt(inx)];
					}
					for(var k = 0, l = inxs.length; k < l; k++){
						serie = c.series[inxs[k]];
						serie.setVisible(status);
					}
				});


				$container.find(".customLegend  .item-group").click(function(){
					var status = this.getAttribute("data-active");
					if(status == null)
						status = true;
					if(status === "false")
						status = false;
					if(status === "true")
						status = true;
					status = !status;
					this.setAttribute("data-active", status);
					switch(this.getAttribute("data-direction")){
						case "line":{
							$(this).parent().find(".item").attr("data-active", !status).click();
						} break;

						case "column":{
							$(this).parent().parent().find('tr td.item:nth-child(' + ($(this).index() + 1) + ')').attr("data-active", !status).click();
						} break;
					}
				});

			});
		}
	}

	initCharts();
});