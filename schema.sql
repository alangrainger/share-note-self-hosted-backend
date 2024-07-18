-- Generation Time: Jul 18, 2024 at 09:45 AM
-- Server version: 9.0.0
-- PHP Version: 8.2.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
                         `id` int NOT NULL,
                         `users_id` int NOT NULL,
                         `filename` char(32) NOT NULL,
                         `filetype` varchar(10) NOT NULL,
                         `created` datetime NOT NULL,
                         `updated` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Indexes for table `files`
--
ALTER TABLE `files`
    ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `filename_filetype` (`filename`,`filetype`) USING BTREE,
  ADD KEY `users_id` (`users_id`),
  ADD KEY `created` (`created`),
  ADD KEY `filename` (`filename`),
  ADD KEY `filetype` (`filetype`);

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
    MODIFY `id` int NOT NULL AUTO_INCREMENT;

COMMIT;
