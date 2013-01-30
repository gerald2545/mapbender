(function($) {

$.widget("mapbender.mbLegend", {
    options: {
        dialogtitle: "Legend view",
        nolegend: "No legend available",
        maxDialogWidth: $(window).width() - 100,
        maxDialogHeight: $(window).height() - 100
    },
    maxImgWidth: 0,
    maxImgHeight: 0,
    elementUrl: null,

    _create: function() {
        if(this.options.target === null
            || this.options.target.replace(/^\s+|\s+$/g, '') === ""
            || !$('#' + this.options.target)){
            alert('The target element "map" is not defined for  a Legend.');
            return;
        }
        var self = this;
        var me = $(this.element);
        this.elementUrl = Mapbender.configuration.elementPath + me.attr('id') + '/';
        $(document).one('mapbender.setupfinished', function() {
            $('#' + self.options.target).mbMap('ready', $.proxy(self._init, self));
        });
    },
    
    _init: function(){
        var self = this;
        var me = $(this.element);
        this.op_sel = "#"+me.attr('id')+" option";
        $(self.element).button().bind('click', $.proxy(self._showAllLegends, self));
    },
    
    _checkMaxImgWidth: function(val){
        if(this.maxImgWidth < val)
            this.maxImgWidth = val;
    },
    
    _checkMaxImgHeight: function(val){
        if(this.maxImgHeight < val)
            this.maxImgHeight = val;
    },
    
    _showAllLegends: function(evt) {
        this.maxImgWidth = 0;
        this.maxImgHeight = 0;
        var self = this;
        var mbMap = $('#' + this.options.target).data('mbMap');
        var layers = mbMap.map.layers();
        var baseLnum = 0;
        var baseLayers = [];
        var otherLayers = [];
        $.each(layers, function(idx, val){
            if (!val.visible()){return ;}
            var temp = self._getLayerLegend(val);
            if(val.options.isBaseLayer) {
                baseLnum++;
                baseLayers = baseLayers.concat(temp); // XXXXXXXXXX
            } else {
                otherLayers = otherLayers.concat(temp); // XXXXXXXXXX
            }
        });
        var options = {autoHeight: false, collapsible: true};
        if (baseLnum > 0 && otherLayers.length > 0) {
            options['active'] = baseLnum;
        }
        this._showLegendDialog(baseLayers.concat(otherLayers), options, 0, "");
    },
    
    showLegend: function(evt) {
        if(typeof(evt) === 'undefined')
            return;
        var legends = this._getLayerLegend(evt.data);
        this._showLegendDialog(legends, {autoHeight: false, collapsible: true}, 0, "");
    },
    
    _getLayerLegend: function(val){
        var legend;
        if(val.options.type == "wms") { // wms & wmc
            if(val.options.wms_parameters) { // wmc
                legend = {
                    title: val.options.label};
                if(val.options.wms_parameters.legend) {
                    legend.urls = val.options.wms_parameters.legend.href;
                }
                legend = [legend];
            } else { // wms
//                var glgUrl = val.olLayer.url + (val.olLayer.url.match(/\?/) ? '&' : '?');
//                glgUrl += 'service=WMS&request=GetLegendGraphic&version=1.1.1&format=image/png&layer=';

                var glgUrl;
                if(val.olLayer.configuration.configuration.legendgraphic) {
                    glgUrl = val.olLayer.configuration.configuration.legendgraphic + "&format=" + val.olLayer.format;
                }
                legend = [];
                $.each(val.options.allLayers, function(idx, val_) {
                    // the val_.legend is determinated from getCapabilities absolute correct !!!
                    var l = {
                        title: val_.title};
                    if(val_.legend) {
                        l.url = val_.legend;
                    } else if(glgUrl) {
                        var url = glgUrl;
                        if(val_.style){
                            url += "&style=" + val_.style;
                        }
                        url += "&layer=" + val_.name;
                        l.url = url;
                    }
                    legend.push(l);
                });
            }
        } else  if(val.options.type == "wmts") { // wmts
            legend = {
                title: val.options.layer
            };
            if(val.options.configuration.configuration.legendurl &&
                val.options.configuration.configuration.legendurl !== "") {
                legend.urls = val.options.configuration.configuration.legendurl;
            }
            legend = [legend];
        }
        return legend;
    },
    
    _showLegendDialog: function(legends, accordionoptions, idx, content){
        var self = this;
        var legend = legends[idx];
        var id = $(self.element).attr("id");
        if(legends.length > idx){
            if(legend.url != null && legend.url.length > 0){
                $("#" + id + "-dialog").html('<img id="testload" style="display: none;" src="' + legend.url + '"></img>');
                $("#" + id + "-dialog img#testload").load(function() {
                    var width = $(this).width(), height = $(this).height();
                    self._checkMaxImgWidth(width);
                    self._checkMaxImgHeight(height);
                    var html = '<h3><a href="#">' + legend.title + '</a></h3>';
                    html += '<div class="legend-img-div"><img src="' + legend.url + '"></img></div>';
                    self._showLegendDialog(legends, accordionoptions, idx + 1, content + html);
                }).error(function() {
                    var html = '<h3><a href="#">' + legend.title + '</a></h3>';
                    html += '<div class="legend-text-div">' + self.options.nolegend + ' </div>';
                    self._showLegendDialog(legends,  accordionoptions, idx + 1, content + html);
                });
            } else {
                var html = '<h3><a href="#">' + legend.title + '</a></h3>';
                html += '<div class="legend-text-div">' + self.options.nolegend + ' </div>';
                self._showLegendDialog(legends, accordionoptions, idx + 1, content + html);
            }
        } else {
            content = '<div id="legend-accordion">' + content + '</div>';
            $("#" + $(self.element).attr("id") + "-dialog").html(content).dialog({
                title: self.options.dialogtitle,
                maxHeight: self.options.maxDialogHeight + "px",
                maxWidth: self.options.maxDialogWidth,
                minWidth:  self.maxImgWidth != 0 ? self.maxImgWidth : 300
//                ,resizable: false
            });
            $("div#legend-accordion").accordion(accordionoptions);
            
            $("#" + $(self.element).attr("id") + "-dialog").css({
                "max-height": (self.options.maxDialogHeight - 50) +"px"
            });
        }
    },
    
    _destroy: $.noop
});

})(jQuery);
