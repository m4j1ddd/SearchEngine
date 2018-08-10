function ajaxFunction(){
    var ajaxRequest;
    
    try{
        ajaxRequest = new XMLHttpRequest();
    } catch (e){
        try{
            ajaxRequest = new ActiveXObject("Msxml2.XMLHTTP");
        } catch (e) {
            try{
                ajaxRequest = new ActiveXObject("Microsoft.XMLHTTP");
            } catch (e){
                return false;
            }
        }
    }
ajaxRequest.open("GET", "/indexer.php", true);
ajaxRequest.send(null);
}