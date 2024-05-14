<?php // Home page

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'km.php';
require_once 'em.php';

// Connect to database
require_once 'login.php';
$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error)
    die($conn->connect_error);

session_start();

// HTML header
echo "<!DOCTYPE html>\n<html><head><title>Home</title>";

//logout function
if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: loginsignup.php');
}

// Must be registered user in order to access home page
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    echo "Welcome back $username.<br><br>";

    // Logout button
    echo <<<_END
     <form method="post" action="home.php">
         <input type="submit" name="logout" value="Logout">
     </form>
     <br>
    _END;

    // TRAIN MODEL: select algorithm, enter model name, upload scores (input file or type scores)
    echo <<<_END
    <div style="display: flex; justify-content: center; align-items: center;">
        <form method="post" action="home.php" enctype='multipart/form-data'>
            <table border="0" cellpadding="2" cellspacing="15" bgcolor="#eeeeee">
                <th colspan="2" align="center">Train Model</th>
                <tr>
                    <td>Choose algorithm:</td>
                    <td><select name='algorithm_dropdown'>
                        <option value=''>Select algorithm</option>
                        <option value="K-Means">K-Means</option>  
                        <option value="Expectation Maximization">Expectation Maximization</option>  
                    </select></td>
                </tr>
                <tr>
                    <td>Model name:</td>
                    <td><input type="text" maxlength="128" name="modelname"></td>
                </tr>
                <tr>
                    <td>Upload Scores:</td>
                    <td><select id="uploadscores_dropdown" name='uploadscores_dropdown' onchange="toggleUploadMethod()">
                        <option value=''>Select method</option>
                        <option value="uploadfile">Upload TXT File</option>  
                        <option value="textbox">Type in text Box</option>  
                    </select></td>
                </tr>
                <tr id="file_input" style="display:none;">
                    <td><input type="file" maxlength="128" name="filename" size="10"></td>
                </tr>
                <tr id="text_box" style="display:none;">
                    <td><textarea name="typedscores" rows="5" cols="50"></textarea></td>
                </tr>
                <tr>
                    <td colspan="2" align="center"><input type="submit" name ="trainmodel" value="Train Model"></td>
                </tr>
            </table>
        </form>
    </div>
    <script>
        function toggleUploadMethod() {
            var dropdown = document.getElementById("uploadscores_dropdown");
            var fileInput = document.getElementById("file_input");
            var textBox = document.getElementById("text_box");

            if (dropdown.value === "uploadfile") {
                fileInput.style.display = "block";
                textBox.style.display = "none";
            } else if (dropdown.value === "textbox") {
                fileInput.style.display = "none";
                textBox.style.display = "block";
            }
        }
    </script>
    _END;

    $scores = "";
    $selected_algorithm = "";

    // Train Model
    if (isset($_POST['trainmodel'])) {

        // Check algorithm is selected
        if ($_POST['algorithm_dropdown'] == '') {
            echo "Error: No algorithm selected.";
            die();
        }
        $selected_algorithm = $_POST['algorithm_dropdown'];

        // Check model name is not empty and unique
        if ($_POST['modelname'] == '') {
            echo "Error: No model name inputted.";
            die();
        } else if (checkModelNameExists(get_post($conn, 'modelname'), $conn)) {
            echo "Error: Model name already exists. Please choose a different name.";
            die();
        }
        $modelname = $_POST['modelname'];

        // Handle scores through file upload
        if (isset($_FILES['filename']) && $_FILES['filename']['error'] === UPLOAD_ERR_OK) {
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
            } else
                echo "'$file' is not an accepted file type.";

            // Scores inputted through text box
        } else if (isset($_POST['typedscores'])) {
            $typedscores = $_POST['typedscores'];

            // Regular expression to match the format x,y
            $regex = '/^\d+,\s?\d+$/m';

            // Check if the input matches the format
            if (preg_match($regex, $typedscores)) {
                $scores = $typedscores;
            } else {
                echo "Error: Please enter coordinates in the format x,y";
                die();
            }
        } else {
            echo "Error: File not found or formatted incorrectly.";
            die();
        }

        // Insert scores into database
        $query = "INSERT INTO scores (username, modelname, scores) VALUES 
            ('$username', '$modelname', '$scores')";
        $result = $conn->query($query);
        if (!$result) {
            echo "INSERT failed: $query<br>" . $conn->error . "<br><br>";
            die();
        }

        // Train the model based on the selected algorithm
        if ($selected_algorithm == 'K-Means') {
            echo "Training model using K-Means algorithm...<br>";
            km($modelname, $scores, $conn);

        } elseif ($selected_algorithm == 'Expectation Maximization') {
            echo "Training model using Expectation Maximization algorithm...<br>";
            expectationMaximization($modelname, $scores, 3, 100, 0.001, $conn);
        }
    }


    // TEST MODEL
    // Choose a trained model, upload scores to test with
    echo <<<_END
    <br>
    <div style="display: flex; justify-content: center; align-items: center;">
        <form method="post" action="home.php" enctype='multipart/form-data'>
            <table border="0" cellpadding="2" cellspacing="15" bgcolor="#eeeeee">
                <th colspan="2" align="center">Test Model using Trained Models</th>
                <tr>
                    <td>Choose a trained model:</td>
                    <td><select name='model_dropdown'>
                        <option value=''>Select model</option>
    _END;

    // Display trained models in dropdown 
    $query = "SELECT * FROM scores where kmid != '' or emid != ''";
    $result = $conn->query($query);
    if (!$result)
        die("Database access failed: " . $conn->error);

    $rows = $result->num_rows;
    for ($j = 0; $j < $rows; ++$j) {
        $result->data_seek($j);
        $row = $result->fetch_array(MYSQLI_NUM);

        $modelname = $row[2];
        $kmid = $row[4];
        $emid = $row[5];

        // Indicate which trained models are KM or EM
        if ($kmid != '')
            $modelname .= '.KM';
        if ($emid != '')
            $modelname .= '.EM';

        echo "<option value='$modelname' $selected>$modelname</option>";
    }
    echo <<<_END
                        </select></td></tr>
                <tr>
                    <td>Upload txt file:</td>
                    <td><input type="file" maxlength="128" name="filename" size="10"></td>
                </tr>
                <tr>
                    <td colspan="2" align="center"><input type="submit" name="testmodel" value="Test Model"></td>
                </tr>
            </table>
        </form>
    </div>
    _END;

    // Test the uploaded scores with the selected trained model
    if (isset($_POST['testmodel'])) {
        $selected_trainedmodel = $_POST['model_dropdown'];

        // Handle file upload
        if ($_FILES) {
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

                // Test the uploaded scores based on the selected trained model
                if (strpos($selected_trainedmodel, 'KM') !== false) {
                    km2($scores, $selected_trainedmodel, $conn);
                } elseif (strpos($selected_trainedmodel, 'EM') != false) {
                    em($scores, $selected_trainedmodel, $conn);
                } else {
                    echo "No test model chosen";
                }
            } else
                echo "'$file' is not an accepted file type";
        } else {
            echo "No file uploaded";
        }
    }
} else
    // If unregistered user accesses this page, redirect them to log in
    echo "Please <a href='loginsignup.php'>click here</a> to log in.";

$conn->close();

function get_post($conn, $var)
{
    return $conn->real_escape_string($_POST[$var]);
}

function checkModelNameExists($modelname, $conn)
{
    // Sanitize the input to prevent SQL injection
    $modelname = $conn->real_escape_string($modelname);

    // Prepare the query
    $query = "SELECT * FROM scores WHERE modelname = '$modelname'";

    // Execute the query
    $result = $conn->query($query);

    if (!$result) {
        // Query execution failed
        die("Query failed: " . $conn->error);
    }

    // Check if any rows were returned
    if ($result->num_rows > 0) {
        // Model name exists
        return true;
    } else {
        // Model name does not exist
        return false;
    }
}
?>