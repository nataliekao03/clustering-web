<?php //home page
//for data.txt:
//1.0, 2.0
//1.5, 1.8
//5.0, 8.0
//8.0, 8.0
// Cluster 1: (1.0, 2.0), (1.5, 1.8)
// Cluster 2: (2.0, 2.0)
// Cluster 3: (5.0, 8.0), (8.0, 8.0)

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

//connect to database
require_once 'login.php';
$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error)
    die($conn->connect_error);

echo "<!DOCTYPE html>\n<html><head><title>Home</title>"; 

session_start();
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    echo "Welcome back $username.<br><br>";

    //upload file 
    echo <<<_END
    <html><head><title>PHP Form Upload</title></head><body>
    <form method='post' action='home.php' enctype='multipart/form-data'>
    Select a TXT File:
    <input type='file' name='filename' size='10'><br><br>
    Enter model name: 
    <input type='text' name='modelname'><br><br>
    <input type='submit' value='Submit Scores'></form>
    _END;

    if ($_FILES) {
        if (isset($_POST['modelname']))
            $modelname = get_post($conn, 'modelname');
        echo $modelname;
        $file = $_FILES['filename']['name'];

        // Must be .txt file type
        if ($_FILES['filename']['type'] == 'text/plain') {

            // Open file
            $fh = fopen($file, 'r') or
                die("File does not exist or you lack permission to open it");

            // Read file
            while (!feof($fh)) {
                $line = fgets($fh);
                $scores .= $line;
            }

            //insert name and text file of scores into table uploaded 
            $query = "INSERT INTO scores VALUES" .
                "('$username', '$modelname', '$scores', '', '')";
            $result = $conn->query($query);
            if (!$result)
                echo "INSERT failed: $query<br>" . $conn->error . "<br><br>";

        } else
            echo "'$file' is not an accepted file type";
    } else
        echo "No text file has been uploaded <br><br>";

    echo "</body></html>";

    // display user's uploaded scores 
    echo "<br><strong> Your Uploaded Scores:</strong><br>";

    $query = "SELECT * FROM scores";
    $result = $conn->query($query);
    if (!$result)
        die("Database access failed: " . $conn->error);

    $rows = $result->num_rows;

    for ($j = 0; $j < $rows; ++$j) {
        $result->data_seek($j);
        $row = $result->fetch_array(MYSQLI_NUM);
        echo <<<_END
        <pre>
        <strong>Username:</strong> $row[0]
        <strong>Model Name:</strong> $row[1]
        <strong>Scores:</strong>
        $row[2]
        </pre>
        <form action="test.php" method="post">
        <input type="hidden" name="modelname" value="$row[1]">
        <input type="hidden" name="scores" value="$row[2]">
        <input type='submit' name='test_model' value='Test Model'>
        </form>
        _END;
    }

    // test and display tested model with new points using k-means clustering
    if (isset($_POST['test_model'])) {
        $_SESSION['username'] = $username;
        $_SESSION['modelname'] = $_POST['modelname'];
        $_SESSION['scores'] = $_POST['scores'];
    }
} else
    // If unregistered user accesses this page, redirect them to log in
    echo "Please <a href='loginsignup.php'>click here</a> to log in.";


$result->close();
$conn->close();

function get_post($conn, $var)
{
    return $conn->real_escape_string($_POST[$var]);
}

?>