-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 24 Mar 2026, 08:21:48
-- Sunucu sürümü: 10.4.32-MariaDB
-- PHP Sürümü: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `para_takip`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `goals`
--

CREATE TABLE `goals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `target_amount` decimal(10,2) NOT NULL,
  `deadline` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `goals`
--

INSERT INTO `goals` (`id`, `user_id`, `title`, `target_amount`, `deadline`, `created_at`) VALUES
(16, 3, 'اصلسيب', 902.73, '2026-03-20', '2026-03-20 18:34:11'),
(17, 2, 'ilk bin dolarim ', 1000.00, NULL, '2026-03-20 20:05:37');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `account_type` varchar(50) DEFAULT 'Main'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `type`, `category`, `amount`, `description`, `created_at`, `account_type`) VALUES
(35, 2, 'income', 'KREDI', 398.00, '', '2026-02-23 17:23:36', 'Ana Cüzdan'),
(36, 2, 'income', 'ANNEM', 205.00, '', '2026-02-23 17:24:04', 'Ana Cüzdan'),
(37, 2, 'income', 'PARAM', 61.64, 'Cebimdeki kendi param', '2026-02-23 17:24:58', 'Ana Cüzdan'),
(51, 2, 'expense', 'PARAM', 16.17, '', '2026-03-02 19:46:56', 'Ana Cüzdan'),
(54, 2, 'income', 'KREDI', 90.72, '', '2026-03-11 07:46:03', 'Ana Cüzdan'),
(55, 2, 'expense', 'PARAM', 45.36, '', '2026-03-11 07:47:07', 'Ana Cüzdan'),
(56, 2, 'income', 'KREDI', 90.27, '', '2026-03-20 20:04:52', 'Ana Cüzdan'),
(57, 2, 'income', 'ANNEM', 45.14, '', '2026-03-20 20:06:12', 'Ana Cüzdan');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `preferred_currency` varchar(10) DEFAULT 'USD'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `preferred_currency`) VALUES
(1, 'Hashem', 'wwfwef@gmail.com', '$2y$10$uOOl3jNrhwwaQCZUmXexLuX3u9lPw3DbXgmBkB9ruw7EFleVGe8Fm', 'user', '2026-02-22 09:01:53', 'USD'),
(2, 'Hashem', 'hasimmohebdi@gmail.com', '$2y$10$VITH6bf3cEdkjUnAFdA7b.XdhstM/zePcjyC6E5WpbF.lxhPtpgVC', 'user', '2026-02-22 09:02:35', 'TRY'),
(3, 'Admin', 'admin@gmail.com', '$2y$10$htRRRBW5tJ6l79ZJDcaflekFKtfpFlXyMXR5k2WPmTBdn03vDNFj6', 'admin', '2026-02-25 09:24:01', 'USD');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_wallets`
--

CREATE TABLE `user_wallets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `goals`
--
ALTER TABLE `goals`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Tablo için indeksler `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD PRIMARY KEY (`id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `goals`
--
ALTER TABLE `goals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Tablo için AUTO_INCREMENT değeri `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `user_wallets`
--
ALTER TABLE `user_wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
