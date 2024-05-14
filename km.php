<?php

require_once 'login.php';

// Called when training model for the first time
function km($modelname, $scores, $conn)
{
    // Parse scores from text file
    $data = [];
    $lines = explode("\n", $scores);
    foreach ($lines as $line) {
        $values = explode(",", $line);
        $data[] = [floatval($values[0]), floatval($values[1])];
    }

    // Set the seed for the random number generator
    $seed = 12345;
    mt_srand($seed);

    // Initialize the 3 cluster centroids randomly
    $centroids = [];
    for ($i = 0; $i < 3; $i++) {
        $centroids[] = [$data[rand(0, count($data) - 1)][0], $data[rand(0, count($data) - 1)][1]];
    }

    // Perform k-means algorithm
    $max_iterations = 100;
    for ($iteration = 0; $iteration < $max_iterations; $iteration++) {
        // Assign each data point to the nearest centroid
        $clusters = [];
        for ($i = 0; $i < count($data); $i++) { // Go through each data as a point
            $point = $data[$i];
            $min_distance = PHP_INT_MAX;
            $closest_centroid = null;
            for ($j = 0; $j < count($centroids); $j++) { // Calculate distance from each centroid
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

    $newmodelname = "$modelname.KM";

    // Dynamically insert results into km based on number of centroids
    $query = "INSERT INTO km (modelname";
    for ($i = 0; $i < count($centroids); $i++) {
        $query .= ", centroid" . ($i + 1) . "x, centroid" . ($i + 1) . "y";
    }
    $query .= ") VALUES ('$newmodelname'";
    for ($i = 0; $i < count($centroids); $i++) {
        $query .= ", " . ($centroidsxy[$i * 2] ?? 'NULL') . ", " . ($centroidsxy[$i * 2 + 1] ?? 'NULL');
    }
    $query .= ");";

    $result = $conn->query($query);
    if (!$result)
        echo("Insertion failed: " . $conn->error);

    // Retrieve the last inserted kmid
    $kmid = $conn->insert_id;

    // Update kmid in scores table to reference kmid in km table
    $query = "UPDATE scores SET kmid = $kmid WHERE modelname = '$modelname'";
    $result = $conn->query($query);
    if (!$result)
        die("Update failed: " . $conn->error);

    // Output the clustering results
    /* echo <<<_END
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
        </table>
    _END; */
}

// called when testing model with trained model
function km2($scores, $selected_trainedmodel, $conn)
{
    // Parse scores from text file
    $data = [];
    $lines = explode("\n", $scores);
    foreach ($lines as $line) {
        $values = explode(",", $line);
        $data[] = [floatval($values[0]), floatval($values[1])];
    }

    // Set the seed for the random number generator
    $seed = 12345;
    mt_srand($seed);

    // Initialize the 3 cluster centroids with the trained model's centroids
    $centroids = [];

    $query = "SELECT * FROM km WHERE modelname = '$selected_trainedmodel'";
    $result = $conn->query($query);
    if (!$result)
        die("Database access failed: " . $conn->error);

    $row = $result->fetch_assoc();
    //print_r($row);

    // Save centroids into a new array
    $centroids = array(
        array($row['centroid1x'], $row['centroid1y']),
        array($row['centroid2x'], $row['centroid2y']),
        array($row['centroid3x'], $row['centroid3y'])
    );

    //print_r($centroids);

    // Perform k-means algorithm
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
    //for ($i = 0; $i < count($centroids); $i++) {
    //    $centroid = $centroids[$i];
    //    $centroidsxy[] = $centroid[0];
    //    $centroidsxy[] = $centroid[1];
    //echo "Centroid " . ($i + 1) . " - X: " . $centroid[0] . ", Y: " . $centroid[1] . "<br>";
    //}
    //print_r($centroidsxy);
    //echo $centroidsxy[0];

    // Output the clustering results
    echo <<<_END
    <div style="display: flex; justify-content: center; align-items: center;">
        <table>
            <tr><td><strong> K-Means Clustering Results with $selected_trainedmodel:</strong><br></td></tr>
            <tr><td>
                <div style="height: 200px; width: 1000px; overflow: auto;">
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
        </table>
    </div>
    _END;
}
?>