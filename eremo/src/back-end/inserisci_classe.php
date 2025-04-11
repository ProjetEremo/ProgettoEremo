<html>
<body>
<?php
    $idconn=mysqli_connect ("localhost", "root", "", "progettoeremo") //conness. al DBMS
    or die ("impossibile connettersi all'host o database sconosciuto");

    //oppure or die(mysqli_connect_error()) se si vuole mantenere la
    //messaggistica d'errore di MySQL

    $Contatto=$_POST['Contatto' ];
    $Nome=$_POST['Nome' ];
    $Cognome=$_POST['Cognome'];
    $Password=$_POST['Password'];

    //inserimento della query da eseguire in una stringa
    $sql="INSERT INTO UtentiRegistrati
          VALUES ('$Contatto','$Nome','$Cognome','$Password','0') ";

    //esecuzione della query sul database
    //chiusura della connessione
    $res=mysqli_query($idconn, $sql);
    mysqli_close($idconn);
?>
</body>
</html>