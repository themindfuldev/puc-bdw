function setImageSwaps() {
  prepareImageSwap(document.body,true,true);
}
function clearImageSwaps() {
  removeAllImageSwaps(document.body);
}

// DO NOW: If the browser is W3 DOM compliant, execute setImageSwaps function
if (document.getElementsByTagName && document.getElementById) {
 if (window.addEventListener) {
   window.addEventListener('load', setImageSwaps, false);
   window.addEventListener('unload', clearImageSwaps, false);
 } else if (window.attachEvent) {
   window.attachEvent('onload', setImageSwaps);
   window.attachEvent('onunload', clearImageSwaps);
 }
}

function prepareImageSwap(elem,mouseOver,mouseOutRestore,mouseDown,mouseUpRestore,mouseOut,mouseUp) {
 //Do not delete these comments.
 //Non-Obtrusive Image Swap Script V1.1 by Hesido.com
 //Attribution required on all accounts
 if (typeof(elem) == 'string') elem = document.getElementById(elem);
 if (elem == null) return;
 var regg = /(.*)(_nm\.)([^\.]{3})$/;
 var prel = new Array(), img, imgList, imgsrc, mtchd;
 imgList = elem.getElementsByTagName('img');
 for (var i=0; img = imgList[i]; i++) {
  if (!img.rolloverSet && img.src.match(regg)) {
   mtchd = img.src.match(regg);
   img.hoverSRC = mtchd[1]+'_hv.'+ mtchd[3];
   img.outSRC = img.src;
   if (typeof(mouseOver) != 'undefined') {
    img.hoverSRC = (mouseOver) ? mtchd[1]+'_hv.'+ mtchd[3] : false;
    img.outSRC = (mouseOut) ? mtchd[1]+'_ou.'+ mtchd[3] : (mouseOver && mouseOutRestore) ? img.src : false;
    img.mdownSRC = (mouseDown) ? mtchd[1]+'_md.' + mtchd[3] : false;
    img.mupSRC = (mouseUp) ? mtchd[1]+'_mu.' + mtchd[3] : (mouseOver && mouseDown && mouseUpRestore) ? img.hoverSRC : (mouseDown && mouseUpRestore) ? img.src : false;
   }
   if (img.hoverSRC) {preLoadImg(img.hoverSRC); img.onmouseover = imgHoverSwap;}
   if (img.outSRC) {preLoadImg(img.outSRC); img.onmouseout = imgOutSwap;}
   if (img.mdownSRC) {preLoadImg(img.mdownSRC); img.onmousedown = imgMouseDownSwap;}
   if (img.mupSRC) {preLoadImg(img.mupSRC); img.onmouseup = imgMouseUpSwap;}
   img.rolloverSet = true;
  }
 }
 function preLoadImg(imgSrc) {
  prel[prel.length] = new Image(); prel[prel.length-1].src = imgSrc;
 }
}
function imgHoverSwap() {this.src = this.hoverSRC;}
function imgOutSwap() {this.src = this.outSRC;}
function imgMouseDownSwap() {this.src = this.mdownSRC;}
function imgMouseUpSwap() {this.src = this.mupSRC;}

function removeImageSwaps(img) {
  img.onmouseover = img.onmouseout = img.onmousedown = img.onmouseup = null;
}
function removeAllImageSwaps(el) {
  var i, imgList = el.getElementsByTagName('img');
  for (i = 0; i < imgList.length; i++)
    removeImageSwaps(imgList[i]);
}
