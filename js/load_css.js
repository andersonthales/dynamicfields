(function() {
    if (!document.querySelector('link[href*="dynamicfields.css"]')) {
        var link = document.createElement('link');
        link.rel  = 'stylesheet';
        link.type = 'text/css';
        link.href = CFG_GLPI.root_doc + '/plugins/dynamicfields/css/dynamicfields.css?v=' + Date.now();
        document.head.appendChild(link);
    }
})();
