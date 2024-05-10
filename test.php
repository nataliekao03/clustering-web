<?php //test model page
echo "<!DOCTYPE html>\n<html><head><title>Test Model</title>";

echo <<<_END
        <form action="home.php" method="post"> 
        <input type='submit' value='Back to Home'>
        </form>
        _END;

session_start();

if (isset($_SESSION['username']) && isset($_SESSION['modelname']) && isset($_SESSION['scores'])) {
    $username = $_SESSION['username'];
    $modelname = $_SESSION['modelname'];
    $scores = $_SESSION['scores'];

    kmeans($scores,$modelname);
} else {
    echo "Session variables not set.";
}

//right now, it only tests the countries dataset.

function kmeans($scores, $modelname)
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

        // Echo iteration clusters
        //echo "<br>Iteration: $iteration<br>";
        //foreach ($clusters as $clusterIdx => $cluster) {
        //    echo "Cluster $clusterIdx:\n";
        //    foreach ($cluster as $point) {
        //        echo "[" . $point[0] . ", " . $point[1] . "]\n";
        //    }
        //}

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
    echo "<br><strong> K-Means Clustering Results for $modelname:</strong><br>";
    foreach ($clusters as $idx => $cluster) {
        echo "Cluster " . ($idx + 1) . ": ";
        foreach ($cluster as $point) {
            echo "(" . $point[0] . ", " . $point[1] . ") ";
        }
        echo "<br><br>";
    }
}
?>