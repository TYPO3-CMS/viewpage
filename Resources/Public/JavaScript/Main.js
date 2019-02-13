/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
define(["require","exports","jquery","TYPO3/CMS/Backend/Storage/Persistent","jquery-ui/resizable"],function(e,t,i,r){"use strict";var n,s;return(s=n||(n={})).resizableContainerIdentifier=".t3js-viewpage-resizeable",s.sizeIdentifier=".t3js-viewpage-size",s.moduleBodySelector=".t3js-module-body",s.customSelector=".t3js-preset-custom",s.customWidthSelector=".t3js-preset-custom",s.customHeightSelector=".t3js-preset-custom-height",s.changeOrientationSelector=".t3js-change-orientation",s.changePresetSelector=".t3js-change-preset",s.inputWidthSelector=".t3js-viewpage-input-width",s.inputHeightSelector=".t3js-viewpage-input-height",s.currentLabelSelector=".t3js-viewpage-current-label",s.topbarContainerSelector=".t3js-viewpage-topbar",new(function(){function e(){var e=this;this.defaultLabel="",this.minimalHeight=300,this.minimalWidth=300,this.storagePrefix="moduleData.web_view.States.",this.queue=[],this.queueIsRunning=!1,i(function(){var t=i(".t3js-preset-custom-label");e.defaultLabel=t.length>0?t.html().trim():"",e.$iframe=i("#tx_this_iframe"),e.$resizableContainer=i(n.resizableContainerIdentifier),e.$sizeSelector=i(n.sizeIdentifier),e.initialize()})}return e.getCurrentWidth=function(){return i(n.inputWidthSelector).val()},e.getCurrentHeight=function(){return i(n.inputHeightSelector).val()},e.setLabel=function(e){i(n.currentLabelSelector).html(e)},e.getCurrentLabel=function(){return i(n.currentLabelSelector).html().trim()},e.prototype.persistQueue=function(){var e=this;if(!1===this.queueIsRunning&&this.queue.length>=1){this.queueIsRunning=!0;var t=this.queue.shift();r.set(t.storageIdentifier,t.data).done(function(){e.queueIsRunning=!1,e.persistQueue()})}},e.prototype.addToQueue=function(e,t){var i={storageIdentifier:e,data:t};this.queue.push(i),this.queue.length>=1&&this.persistQueue()},e.prototype.setSize=function(e,t){isNaN(t)&&(t=this.calculateContainerMaxHeight()),t<this.minimalHeight&&(t=this.minimalHeight),isNaN(e)&&(e=this.calculateContainerMaxWidth()),e<this.minimalWidth&&(e=this.minimalWidth),i(n.inputWidthSelector).val(e),i(n.inputHeightSelector).val(t),this.$resizableContainer.css({width:e,height:t,left:0})},e.prototype.persistCurrentPreset=function(){var t={width:e.getCurrentWidth(),height:e.getCurrentHeight(),label:e.getCurrentLabel()};this.addToQueue(this.storagePrefix+"current",t)},e.prototype.persistCustomPreset=function(){var t={width:e.getCurrentWidth(),height:e.getCurrentHeight()};i(n.customSelector).data("width",t.width),i(n.customSelector).data("height",t.height),i(n.customWidthSelector).html(t.width),i(n.customHeightSelector).html(t.height),this.addToQueue(this.storagePrefix+"custom",t)},e.prototype.persistCustomPresetAfterChange=function(){var e=this;clearTimeout(this.queueDelayTimer),this.queueDelayTimer=window.setTimeout(function(){e.persistCustomPreset()},1e3)},e.prototype.initialize=function(){var t=this;i(document).on("click",n.changeOrientationSelector,function(){var e=i(n.inputHeightSelector).val(),r=i(n.inputWidthSelector).val();t.setSize(e,r),t.persistCurrentPreset()}),i(document).on("change",n.inputWidthSelector,function(){var r=i(n.inputWidthSelector).val(),s=i(n.inputHeightSelector).val();t.setSize(r,s),e.setLabel(t.defaultLabel),t.persistCustomPresetAfterChange()}),i(document).on("change",n.inputHeightSelector,function(){var r=i(n.inputWidthSelector).val(),s=i(n.inputHeightSelector).val();t.setSize(r,s),e.setLabel(t.defaultLabel),t.persistCustomPresetAfterChange()}),i(document).on("click",n.changePresetSelector,function(r){var n=i(r.currentTarget).data();t.setSize(parseInt(n.width,10),parseInt(n.height,10)),e.setLabel(n.label),t.persistCurrentPreset()}),this.$resizableContainer.resizable({handles:"w, sw, s, se, e"}),this.$resizableContainer.on("resizestart",function(e){i(e.currentTarget).append('<div id="this-iframe-cover" style="z-index:99;position:absolute;width:100%;top:0;left:0;height:100%;"></div>')}),this.$resizableContainer.on("resize",function(r,s){s.size.width=s.originalSize.width+2*(s.size.width-s.originalSize.width),s.size.height<t.minimalHeight&&(s.size.height=t.minimalHeight),s.size.width<t.minimalWidth&&(s.size.width=t.minimalWidth),i(n.inputWidthSelector).val(s.size.width),i(n.inputHeightSelector).val(s.size.height),t.$resizableContainer.css({left:0}),e.setLabel(t.defaultLabel)}),this.$resizableContainer.on("resizestop",function(){i("#viewpage-iframe-cover").remove(),t.persistCurrentPreset(),t.persistCustomPreset()})},e.prototype.calculateContainerMaxHeight=function(){this.$resizableContainer.hide();var e=i(n.moduleBodySelector),t=e.outerHeight()-e.height(),r=i(document).height(),s=i(n.topbarContainerSelector).outerHeight();return this.$resizableContainer.show(),r-t-s-8},e.prototype.calculateContainerMaxWidth=function(){this.$resizableContainer.hide();var e=i(n.moduleBodySelector),t=e.outerWidth()-e.width(),r=i(document).width();return this.$resizableContainer.show(),parseInt(r-t+"",10)},e}())});