<?php //home page

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connect to database
require_once 'login.php';
$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error)
    die($conn->connect_error);

session_start();

// HTML header
echo "<!DOCTYPE html>\n<html><head><title>Home</title>";

// Must be registered user in order to access home page
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    echo "Welcome back $username.<br><br>";

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

    $selected_model = "";
    $selected_scores = "";

    // Get the selected model name and scores to test
    if (isset($_POST['model_dropdown'])) {
        $selected_model = $_POST['model_dropdown'];

        if (!empty($selected_model)) {
            $selected_scores = getScores($username, $selected_model, $conn);

            echo <<<_END
            <form action="home.php" method="post">
            <input type="hidden" name="selected_model" value="$selected_model">
            <input type="hidden" name="selected_scores" value="$selected_scores">
                <table>
                    <tr><td><strong>Selected Model:</strong> $selected_model</td></tr>
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
        kmeans($_POST['selected_model'], $_POST['selected_scores']);
    }
    if (isset($_POST['em'])) {
        em($selected_model, $selected_scores);
    }

} else
    // If unregistered user accesses this page, redirect them to log in
    echo "Please <a href='loginsignup.php'>click here</a> to log in.";

$conn->close();

function em($modelname, $scores)
{
    $em = "";
    echo <<<_END
        <table>
            <tr><td><strong>Selected Model:</strong> $modelname</td></tr>
            <tr><td>
                <div style="height: 300px; width: 800px; overflow: auto;">
                    <strong>EM:</strong><br>$em;
                </div>
            </td></tr>
            <tr><td><input type='submit' name='km' value='K-Means'>
            <input type='submit' name='em' value='Expectation Maximization'></td></tr>
        </table>
    _END;
}
function kmeans($modelname, $scores)
{
    // Parse scores from text file
    $data = [];
    $lines = explode("\n", $scores);
    foreach ($lines as $line) {
        $values = explode(",", $line);
        $data[] = [floatval($values[0]), floatval($values[1])];
    }

    // Number of clusters = 3
    $k = 3;

    // Initialize cluster centroids randomly
    $centroids = [];
    for ($i = 0; $i < $k; $i++) {
        $centroids[] = [$data[rand(0, count($data) - 1)][0], $data[rand(0, count($data) - 1)][1]];
    }

    // K-means clustering
    $max_iterations = 100;
    for ($iteration = 0; $iteration < $max_iterations; $iteration++) {
        // Assign each data point to the nearest centroid
        $clusters = [];
        foreach ($data as $point) {
            $min_distance = PHP_INT_MAX;
            $closest_centroid = null;
            foreach ($centroids as $idx => $centroid) {
                $distance = sqrt(pow($point[0] - $centroid[0], 2) + pow($point[1] - $centroid[1], 2));
                if ($distance < $min_distance) {
                    $min_distance = $distance;
                    $closest_centroid = $idx;
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
        foreach ($centroids as $idx => $centroid) {
            if (abs($centroid[0] - $new_centroids[$idx][0]) > 0.001 || abs($centroid[1] - $new_centroids[$idx][1]) > 0.001) {
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

function getScores($username, $selected_model, $conn)
{
    $query = "SELECT scores FROM scores WHERE username = '$username' AND modelname = '$selected_model'";
    $result = $conn->query($query);
    if (!$result)
        die("Database access failed: " . $conn->error);

    $row = $result->fetch_assoc();
    return $row['scores'];
}

// i created this searchModelName function but did not use 
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

?>