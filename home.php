<?php //home page

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
if(isset($_POST['logout'])){
    session_unset();
    session_destroy();
    header('Location: loginsignup.php');
}

// Must be registered user in order to access home page
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    echo "Welcome back $username.<br><br>";

    //logout button
    echo <<<_END
        <form method="post" action="home.php">
            <input type="submit" name="logout" value="Logout">
        </form>
        <br>
    _END;

    // Upload form 
    echo <<<_END
    <div style="display: flex; justify-content: center; align-items: center;">
        <form method="post" action="home.php" enctype='multipart/form-data'>
            <table border="0" cellpadding="2" cellspacing="15" bgcolor="#eeeeee">
                <th colspan="2" align="center">Upload Scores</th>
                <tr>
                    <td>Enter model name:</td>
                    <td><input type="text" maxlength="128" name="modelname"></td>
                </tr>
                <tr>
                    <td>Upload txt file:</td>
                    <td><input type="file" maxlength="128" name="filename" size="10"></td>
                </tr>
                <tr>
                    <td>Or type scores:</td>
                    <td><input type="text" maxlength="128" name="typedscores"></td>
                </tr>
                <tr>
                    <td colspan="2" align="center"><input type="submit" value="Submit Scores"></td>
                </tr>
            </table>
        </form>
    </div>
    _END;

    $scores = "";
    // Handle file upload
    if ($_FILES) {
        if (isset($_POST['modelname']))
            $modelname = get_post($conn, 'modelname');

        $modelExists = checkModelNameExists($modelname, $conn);

        if ($modelExists) {
            echo "Error: Model name '$modelname' already exists. Please choose a different name.";
        } else {
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

                // Insert scores into database
                $query = "INSERT INTO scores (username, modelname, scores) VALUES 
                    ('$username', '$modelname', '$scores')";
                $result = $conn->query($query);
                if (!$result)
                    echo "INSERT failed: $query<br>" . $conn->error . "<br><br>";

            } else
                echo "'$file' is not an accepted file type";
        }
    }


    // Display user's scores in dropdown menu
    echo <<<_END
    <br>
    <div style="display: flex; justify-content: center; align-items: center;">
    <form method='post' action='home.php' id='testForm'>
        <table border="0" cellpadding="2" cellspacing="15" bgcolor="#eeeeee" style="width: 800px;">
            <th colspan="2" align="center">Train/Test Model</th>
            <tr>
                <td colspan="2" align="center">Choose a model to test:
                    <select name='model_dropdown' id='model_dropdown' onchange='submitForm()'>
                        <option value=''>Select model</option>
    _END;

    $query = "SELECT * FROM scores WHERE username = '$username'";
    $result = $conn->query($query);
    if (!$result)
        die("Database access failed: " . $conn->error);

    $rows = $result->num_rows;
    for ($j = 0; $j < $rows; ++$j) {
        $result->data_seek($j);
        $row = $result->fetch_array(MYSQLI_NUM);

        $modelname = $row[2];
        $selected = ($_POST['model_dropdown'] ?? '') === $modelname ? 'selected' : ''; // Check if this option is selected
        echo "<option value='$modelname' $selected>$modelname</option>";
    }

    echo <<<_END
                    </select></td></tr>
    </form>
        <script>
        function submitForm() {
            document.getElementById('testForm').submit();
        }
        </script>
    _END;

    // Get the selected model name and scores to test
    if (isset($_POST['model_dropdown'])) {
        $selected_modelname = $_POST['model_dropdown'];

        if (!empty($selected_modelname)) {
            $row = getScoresRow($username, $selected_modelname, $conn);
            //print_r($row);
            $selected_scoresid = $row['scoresid'];
            $selected_scores = $row['scores'];

            echo <<<_END
            <form action="home.php" method="post">
            <intput type="hidden" name="selected_scoresid" value="$selected_scoresid">
            <input type="hidden" name="selected_modelname" value="$selected_modelname">
            <input type="hidden" name="selected_scores" value="$selected_scores">
                <table>
                    <tr><td><strong>Selected Model:</strong> $selected_modelname</td></tr>
                    <tr><td>
                        <div style="height: 300px; width: 800px; overflow: auto;">
                            <strong>Scores:</strong><br>$selected_scores
                        </div>
                    </td></tr>
                    <tr><td><input type='submit' name='km' value='K-Means'>
                    <input type='submit' name='em' value='Expectation Maximization'></td></tr>
                </table>
            </form>
            </div>
            _END;
        }
    }
    if (isset($_POST['km'])) {
        kmeans($_POST['selected_scoresid'], $_POST['selected_modelname'], $_POST['selected_scores'], $conn);
    }
    if (isset($_POST['em'])) {
        em($_POST['selected_scoresid'], $_POST['selected_modelname'], $_POST['selected_scores'], $conn);
    }

} else
    // If unregistered user accesses this page, redirect them to log in
    echo "Please <a href='loginsignup.php'>click here</a> to log in.";

$conn->close();

function em($scoresid, $modelname, $scores, $conn)
{
    //parse input data
    $data = [];
    $lines = explode("\n", $scores);
    foreach ($lines as $line) {
        $values = explode(",", $line);
        $data[] = [floatval($values[0]), floatval($values[1])];
    }

    //initialize EM parameters
    $num_clusters = 3;      //number of clusters
    $max_iterations = 100;  //max number of iterations
    $tolerance = 0.001;     //tolerance for convergence

    //perform EM algorithm
    $cluster_means = expectationMaximization($data, $num_clusters, $max_iterations, $tolerance);

    //output results 
    $em_output = "Cluster means:<br>";
    foreach ($cluster_means as $index => $mean) {
        $em_output .= "Cluster " . ($index + 1) . ": (" . implode(", ", $mean) . ")<br>";
    }

    // Insert EM results into the database
    $centroid1x = $cluster_means[0][0];
    $centroid1y = $cluster_means[0][1];
    $centroid2x = $cluster_means[1][0];
    $centroid2y = $cluster_means[1][1];
    $centroid3x = $cluster_means[2][0];
    $centroid3y = $cluster_means[2][1];

    $query = "INSERT INTO em (modelname, iteration, centroid1x, centroid1y, centroid2x, centroid2y, centroid3x, centroid3y) 
          VALUES ('$modelname', $max_iterations, '$centroid1x', '$centroid1y', '$centroid2x', '$centroid2y', '$centroid3x', '$centroid3y')";

    // Execute the query
    $result = $conn->query($query);
    if (!$result) {
        die("Insertion failed: " . $conn->error);
    }

    // Display EM output
    echo <<<_END
        <table>
            <tr><td><strong>Selected Model:</strong> $modelname</td></tr>
            <tr><td>
                <div style="height: 300px; width: 800px; overflow: auto;">
                    <strong>EM Output:</strong><br>$em_output
                </div>
            </td></tr>
            <tr><td><input type='submit' name='km' value='K-Means'>
            <input type='submit' name='em' value='Expectation Maximization'></td></tr>
        </table>
    _END;
}


//kmeans
function kmeans($scoresid, $modelname, $scores, $conn)
{
    // Parse scores from text file
    $data = [];
    $lines = explode("\n", $scores);
    foreach ($lines as $line) {
        $values = explode(",", $line);
        $data[] = [floatval($values[0]), floatval($values[1])];
    }

    // Initialize the 3 cluster centroids randomly
    $centroids = [];
    for ($i = 0; $i < 3; $i++) {
        $centroids[] = [$data[rand(0, count($data) - 1)][0], $data[rand(0, count($data) - 1)][1]];
    }

    // K-means clustering
    $max_iterations = 100;
    for ($iteration = 0; $iteration < $max_iterations; $iteration++) {
        // Assign each data point to the nearest centroid
        $clusters = [];
        for ($i = 0; $i < count($data); $i++) { // go through each data as a point
            $point = $data[$i];
            $min_distance = PHP_INT_MAX;
            $closest_centroid = null;
            for ($j = 0; $j < count($centroids); $j++) { // calculate distance from each centroid
                $centroid = $centroids[$j];
                $distance = sqrt(pow($point[0] - $centroid[0], 2) + pow($point[1] - $centroid[1], 2));
                if ($distance < $min_distance) {
                    $min_distance = $distance;
                    $closest_centroid = $j;
                }
            }
            $clusters[$closest_centroid][] = $point;
        }

        // Update centroids
        $new_centroids = [];
        foreach ($clusters as $cluster) {
            $sum_x = 0;
            $sum_y = 0;
            foreach ($cluster as $point) {
                $sum_x += $point[0];
                $sum_y += $point[1];
            }
            $new_centroids[] = [count($cluster) > 0 ? $sum_x / count($cluster) : 0, count($cluster) > 0 ? $sum_y / count($cluster) : 0];
        }

        // Check for convergence
        $converged = true;
        for ($i = 0; $i < count($centroids); $i++) {
            $centroid = $centroids[$i];
            if (abs($centroid[0] - $new_centroids[$i][0]) > 0.001 || abs($centroid[1] - $new_centroids[$i][1]) > 0.001) {
                $converged = false;
                break;
            }
        }

        // Update centroids and break if converged
        if ($converged) {
            $centroids = $new_centroids;
            break;
        } else {
            $centroids = $new_centroids;
        }
    }

    // Iterate over centroids and print out coordinates
    for ($i = 0; $i < count($centroids); $i++) {
        $centroid = $centroids[$i];
        $centroidsxy[] = $centroid[0];
        $centroidsxy[] = $centroid[1];
        echo "Centroid " . ($i + 1) . " - X: " . $centroid[0] . ", Y: " . $centroid[1] . "<br>";
    }
    //print_r($centroidsxy);
    //echo $centroidsxy[0];

    // Insert results (centroids) into km table to train other models  
    $query = "INSERT INTO km (modelname, centroid1x, centroid1y, centroid2x, centroid2y, centroid3x, centroid3y) 
                VALUES ('$modelname', $centroidsxy[0], $centroidsxy[1], $centroidsxy[2], $centroidsxy[3], $centroidsxy[4], $centroidsxy[5])";
    $result = $conn->query($query);
    if (!$result)
        die("Insertion failed: " . $conn->error);

    // Output the clustering results
    echo <<<_END
        <table>
            <tr><td><strong> K-Means Clustering Results for $modelname:</strong><br></td></tr>
            <tr><td>
                <div style="height: 300px; width: 800px; overflow: auto;">
    _END;
    foreach ($clusters as $idx => $cluster) {
        echo "Cluster " . ($idx + 1) . ": ";
        foreach ($cluster as $point) {
            echo "(" . $point[0] . ", " . $point[1] . ") ";
        }
        echo "<br><br>";
    }
    echo <<<_END
                </div>
            </td></tr>
            <tr><td><input type='submit' name='km' value='K-Means'>
            <input type='submit' name='em' value='Expectation Maximization'></td></tr>
        </table>
    _END;
}

function getScoresRow($username, $selected_model, $conn)
{
    $query = "SELECT * FROM scores WHERE username = '$username' AND modelname = '$selected_model'";
    $result = $conn->query($query);
    if (!$result)
        die("Database access failed: " . $conn->error);

    $row = $result->fetch_assoc();
    return $row;
}

// did not use yet - created to ensure unique model names
function searchModelName($modelname, $conn)
{
    $query = "SELECT * FROM advisors WHERE modelname == $modelname";
    $result = $conn->query($query);
    if (!$result)
        die("Error" . $conn->error);

    $exists = $result->num_rows > 0;
    return $exists;
}
function get_post($conn, $var)
{
    return $conn->real_escape_string($_POST[$var]);
}

function checkModelNameExists($modelname, $conn) {
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