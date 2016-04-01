$d.ready(function(){
	window.taggles = {};
	setInterval(function(){
		$(".taggle").each(function(){
			if(this.getAttribute("data-taggle-inited"))
				return;
			if(this.getAttribute("id"))
				initTaggle(this.getAttribute("id"));
			else {
				var id = "";
				do {
					id = Math.random().toString(36).replace(/[^a-z]+/g, '').substr(0, 15);
				} while(gei(id));
				this.setAttribute("id", id);
				initTaggle(id);
			}
		});
	},100);
	function initTaggle(id){
		var elem = gei(id);
		if(!elem){
			console.warn("Taggling unexistent id", id);
			return;
		}
		elem.setAttribute("data-taggle-inited", true);
		var values = elem.getAttribute("data-values");
		if(values)
			values = values.split(",").map(function(elem, indx, arr){return elem.trim()});
		var mode = elem.getAttribute("data-list-mode");
		var list = elem.getAttribute("data-list");
		if(list)
			list = list.split(",").map(function(elem, indx, arr){return elem.trim()});


		var opts = {
			placeholder: elem.getAttribute("title") ? elem.getAttribute("title") : "Enter values",
			submitKeys: [188, 9, 13, 32],
			saveOnBlur: true
		};
		if(mode == "only"){
			opts = $.extend(opts, {
				allowedTags: list
			});
		} else if(mode == "except"){
			opts = $.extend(opts, {
				disallowedTags: list
			});
		}
		if(values){
			opts = $.extend(opts, {
				tags: values
			});
		}
		console.log(opts);
		window.taggles[id] = new Taggle(id, opts);
	}
	if($(".simpleajaxform").length > 0){
		$(".simpleajaxform").submit(function(){
			console.log(this, window.taggles["pluginsMonitored"].getTags());
		});
	}
});