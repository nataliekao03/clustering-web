<?php

require_once 'login.php';
$conn = new mysqli($hn, $un, $pw, $db);
if ($conn->connect_error)
    die($conn->connect_error);


function expectationMaximization($data, $num_clusters, $max_iterations, $tolerance){
    
    // Initialize cluster means randomly
    $num_data = count($data);
    $num_dimensions = count($data[0]);
    $cluster_means = [];
    for ($i = 0; $i < $num_clusters; $i++) {
        $random_data_index = rand(0, $num_data - 1);
        $cluster_means[$i] = $data[$random_data_index];
    }

     // Step 2: EM algorithm iterations
     for ($iteration = 0; $iteration < $max_iterations; $iteration++) {
        // Step 3: Expectation step - calculate cluster probabilities
        $cluster_probs = [];
        for ($i = 0; $i < $num_data; $i++) {
            $point = $data[$i];
            $prob_sum = 0;
            for ($j = 0; $j < $num_clusters; $j++) {
                $distance = 0;
                for ($k = 0; $k < $num_dimensions; $k++) {
                    $distance += pow($point[$k] - $cluster_means[$j][$k], 2);
                }
                $cluster_probs[$i][$j] = exp(-$distance / 2); // Gaussian distribution assumption
                $prob_sum += $cluster_probs[$i][$j];
            }
            // Normalize probabilities
            // Normalize probabilities
for ($j = 0; $j < $num_clusters; $j++) {
    if ($prob_sum != 0) {
        $cluster_probs[$i][$j] /= $prob_sum;
    } else {
        // Handle the case where the denominator is zero
        $cluster_probs[$i][$j] = 1 / $num_clusters; // Assign equal probabilities if sum is zero
    }
}

        }

        // Step 4: Maximization step - update cluster means
        $old_means = $cluster_means;
        for ($j = 0; $j < $num_clusters; $j++) {
            for ($k = 0; $k < $num_dimensions; $k++) {
                $numerator_sum = 0;
                $denominator_sum = 0;
                for ($i = 0; $i < $num_data; $i++) {
                    $numerator_sum += $cluster_probs[$i][$j] * $data[$i][$k];
                    $denominator_sum += $cluster_probs[$i][$j];
                }
                $cluster_means[$j][$k] = $numerator_sum / $denominator_sum;
            }
        }

        // Step 5: Check for convergence
        $max_change = 0;
        for ($j = 0; $j < $num_clusters; $j++) {
            for ($k = 0; $k < $num_dimensions; $k++) {
                $change = abs($old_means[$j][$k] - $cluster_means[$j][$k]);
                if ($change > $max_change) {
                    $max_change = $change;
                }
            }
        }
        if ($max_change < $tolerance) {
            break; // Convergence reached
        }
    }

    // Step 6: Return the cluster means
    return $cluster_means;
}




?>