$doc.ready(function(){
	var tagglePlugins = new Taggle('pluginsMonitored', {
		placeholder: "Plugin slug",
		submitKeys: [188, 9, 13, 32],
		saveOnBlur: true
	});
	console.log("Options init",tagglePlugins);
	$(".simpleajaxform").submit(function(){
		console.log(this, tagglePlugins.getTags());
	});
});