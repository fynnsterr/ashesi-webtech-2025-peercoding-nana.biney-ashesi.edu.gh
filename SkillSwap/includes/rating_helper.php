<?php
/**
 * Rating Helper
 * Handles calculation and storage of user ratings as a workaround for database trigger limitations.
 */

if (!function_exists('updateUserAvgRating')) {
    /**
     * Recalculates and updates the average rating and review count for a user.
     * 
     * @param PDO $pdo The database connection
     * @param int $userId The ID of the user to update
     * @return bool True on success, false on failure
     */
    function updateUserAvgRating($pdo, $userId) {
        if (!$userId) return false;

        try {
            // Calculate avg and count from approved reviews
            $stmt = $pdo->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
                FROM exchange_reviews 
                WHERE reviewee_id = ? AND is_approved = 1
            ");
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $avgRating = $result['avg_rating'] ?: 0.00;
            $reviewCount = $result['review_count'] ?: 0;

            // Update users table
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET rating_avg = ?, rating_count = ? 
                WHERE user_id = ?
            ");
            return $updateStmt->execute([$avgRating, $reviewCount, $userId]);
        } catch (PDOException $e) {
            error_log("Error updating user rating for user $userId: " . $e->getMessage());
            return false;
        }
    }
}
