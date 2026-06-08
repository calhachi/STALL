<?php
function dbConnect()
{
    try {
        $dbh = new PDO(
            $_ENV['DB_DSN'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASS']
        );

        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $dbh;
    } catch (PDOException $e) {
        error_log($e->getMessage());
        header('Location: ' . $_ENV['APP_URL'] . '/error.php');
        exit();
    }
}
// function dbConnect()
// {
//     try {
//         $pdo = new PDO(
//             'mysql:host=mysql80-2.lolipop.lan;dbname=LAA1706258-scenallione;charset=utf8mb4',
//             'LAA1706258',
//             'Pg5bb8jbCL'
//         );

//         $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//         return $pdo;
//     } catch (PDOException $e) {
//         echo $e->getMessage();
//         exit;
//     }
// }
