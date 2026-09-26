-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 26, 2026 at 02:42 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `edoc`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `aemail` varchar(255) NOT NULL,
  `apassword` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`aemail`, `apassword`) VALUES
('admin@edoc.com', '123');

-- --------------------------------------------------------

--
-- Table structure for table `appointment`
--

CREATE TABLE `appointment` (
  `appoid` int(11) NOT NULL,
  `pid` int(11) DEFAULT NULL,
  `apponum` int(11) DEFAULT NULL,
  `scheduleid` int(11) DEFAULT NULL,
  `appodate` date DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `appointment`
--

INSERT INTO `appointment` (`appoid`, `pid`, `apponum`, `scheduleid`, `appodate`) VALUES
(1, 1, 1, 1, '2022-06-03'),
(2, 3, 2, 1, '2026-08-09'),
(3, 3, 1, 9, '2026-08-09');

-- --------------------------------------------------------

--
-- Table structure for table `doctor`
--

CREATE TABLE `doctor` (
  `docid` int(11) NOT NULL,
  `docemail` varchar(255) DEFAULT NULL,
  `docname` varchar(255) DEFAULT NULL,
  `docpassword` varchar(255) DEFAULT NULL,
  `docnic` varchar(15) DEFAULT NULL,
  `doctel` varchar(15) DEFAULT NULL,
  `specialties` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `doctor`
--

INSERT INTO `doctor` (`docid`, `docemail`, `docname`, `docpassword`, `docnic`, `doctel`, `specialties`) VALUES
(1, 'doctor@edoc.com', 'Test Doctor', '123', '000000000', '0110000000', 1),
(2, 'andyhiggens344@gmail.com', 'Andrew Higgens', '123', '124225232', '124225232', 1),
(3, 'Aaanshu@doc.com', 'Dr Aaanshu', 'c3zs8byCUg', '12345', '0478965456', 12),
(4, 'doctor@uu.com', 'Dr Ajula', '4SzyN69sBa', 'aa', '0478965456', 1),
(5, 'doctor@aud.com', 'Dr Hari', 'uZKcNpejW2', '1212', '0478965456', 20),
(6, 'doctor@au.com', 'Dr teja', '6RS7eM4Gj3', '4343', '0478965457', 13);

-- --------------------------------------------------------

--
-- Table structure for table `doctor_2fa`
--

CREATE TABLE `doctor_2fa` (
  `docid` int(11) NOT NULL,
  `secret` varchar(255) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_2fa`
--

INSERT INTO `doctor_2fa` (`docid`, `secret`, `enabled`) VALUES
(1, 'MEYU3GTM6AXYKDXATEB7VNEWQOIWUWH2', 1),
(3, 'ZBC7TNNQS7TT67FVGAWRIRNCR7G7ZHFC', 1),
(4, '6PDQ3INNWH52D7P3QXL2T3OMA7UGTSSG', 1),
(5, 'YNDUHMZVH6MPIS23L7OOX4X47YA6PMHV', 1),
(6, 'GCTPATRRCJSLALBCJKQ45SQZS2GXLJZ4', 1);

-- --------------------------------------------------------

--
-- Table structure for table `doctor_2fa_backup_codes`
--

CREATE TABLE `doctor_2fa_backup_codes` (
  `id` int(11) NOT NULL,
  `docid` int(11) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_2fa_backup_codes`
--

INSERT INTO `doctor_2fa_backup_codes` (`id`, `docid`, `code_hash`, `used`) VALUES
(1, 1, '$2y$10$bOsOIr45cx0NWsqmV9iA7OKoCei7i/cTynukCHwLSqacEFthQvlBi', 1),
(2, 1, '$2y$10$sHXihUnq3yFe81LLyb42JOPjkvY9agypnkkt/UeDUzEw7e5f13EAu', 0),
(3, 1, '$2y$10$g11fneQMJmhKoZR1pR.PU.7NK5KgVXcJmqU0p9fA.1Ddd5TW2at8S', 0),
(4, 1, '$2y$10$l4FaBl8CjdJW8MIjhV6H2eqT/qCm6hbbOqc3JFIA2fvxmNsL47Aom', 0),
(5, 1, '$2y$10$4HrEKBBiWeR2aoBV0E5jYeDJJNH84nGHHr6eQrX5Au3hXD6tzZJFq', 0),
(6, 1, '$2y$10$4gdxDm66N1Cb6MIxstOvr.W8mkIhiEuiGkt2aRWdVomk.KBE2DIsK', 0),
(7, 1, '$2y$10$gi08A.bztHK59XM.dfgmXeD6F2pndPHK3ZV6hBtoDq2f3aneuEkQS', 0),
(8, 1, '$2y$10$D5ffp4VokLarcmSSQokmLuL2ZyP7Vc4dV9ZV2EGDHkwPnarzlPhEm', 0),
(9, 3, '$2y$10$TXpClo8I/VhXB6jfjnR3/uwGpDeGCvfNwI31HtG82pbwfSVHQxwFu', 0),
(10, 3, '$2y$10$kLa82ncZikTboIy.JYnZLOZAm28CNQahP5.XpyMj.9VvLyiY/LTDK', 0),
(11, 3, '$2y$10$M2xMH1EunXREuRtY7bZ79O1cr3wcjnTQVZfnPk21zD4d285ShWLEu', 0),
(12, 3, '$2y$10$Qztu2M0okulOlMo2beHi4u3Ki8Zdrb4ZpY.3WuHGp90dzKovQlrBe', 0),
(13, 3, '$2y$10$mwwxFbS3R3Vdpwygwv3SOuBOWIRcqXBa0QMtcfoRLcXssp5gCM3qi', 0),
(14, 3, '$2y$10$o5qpuY1KmPG4Ok2IfSLA1up7qpIy4Lz30ZNe36TeqPr20QAA.w3Ua', 0),
(15, 3, '$2y$10$OcYZi/5h0kHmVBf5EW8rZeAkveCaJ4q.czucc8ZZCU9nKblHBBspu', 0),
(16, 3, '$2y$10$Wm6eNgnAEqFdMVeUVjTBHeQo7w9Opb.RLk84.xhVXdEOdhfZImojK', 0),
(17, 4, '$2y$10$1CC08.l3Ij0uHY3bied9IO7LoCC5WOOOwXECrnOHGKbk1OxP5tN7S', 0),
(18, 4, '$2y$10$BEEE.WSOnqZUryaxxfI6sOybWGGmLKpkKIObhr/FN6c2a0PSAUa2i', 0),
(19, 4, '$2y$10$P68uiUfYrXjIf1fgErG/qePGQv.tkIE96Ef3FgRaSrpC0pUOepeBm', 0),
(20, 4, '$2y$10$5FNU91QMh/evrW1krkhizuB8fIChPMuoCvESueMhcr8aCBGMl1EpC', 0),
(21, 4, '$2y$10$ulPlIFuLoUgjT80CGEi.4eYxRvt7OqOuzIny0Wl9hA1v7X1VLBzhq', 0),
(22, 4, '$2y$10$3bKmVf.8xDT35oeJE1lvb.CFXeN.3ZXFxL9PjNKboDrwBfnj/raBC', 0),
(23, 4, '$2y$10$5viCjn1xmmVNj0yi6uieXuP81uKdmustAtja7jKqjXlEVzyWshq4m', 0),
(24, 4, '$2y$10$yJh4f.nY5t90/W6OOSAQ6.CLm1FLg.A8mq.cBKBCyhu.b2F7ubCwm', 0),
(25, 5, '$2y$10$DyGtam..5a.PwWHggpqNOOIook1rJQkJtWzHjqqhLWIcy2EXmSTWC', 0),
(26, 5, '$2y$10$bE4qwvirI1WdTRmmlT47JObhbmBA/QbJ37gkX7XIfUpubPSPZALF2', 0),
(27, 5, '$2y$10$Ao6tDQBUZUEeFiuhW7zv9eaKFZfKjqLwvHuSwdTmIGdkWaNrnmtVm', 0),
(28, 5, '$2y$10$bFnoeIsASZb6.9CSvc9mb../3WywkQAzQz47wp93x9Zh9xXTsOJc2', 0),
(29, 5, '$2y$10$GQHTqQbrWxsANWG1k5Hhou9XLdNf4cJGmqXJkLDuqZyo034uTJL/C', 0),
(30, 5, '$2y$10$J6wURKYqZGCSuEJvqvZ1w.iFTqaUbvV7SzZvNK8ZPbbb1OaaCXusq', 0),
(31, 5, '$2y$10$lmPxlLaeeOBEDmT6W4IJduTcUR1Sqv1GzV7d5Mbl5Ul5vUzN59e9G', 0),
(32, 5, '$2y$10$pJTRUNN9loEMh3o96N4IU.SLRfMEBVNXaOmRbz9RFJTTd6p5h6GOi', 0),
(33, 6, '$2y$10$yAphPYafM9ioIRlhdOTEgu97GZpIhwRaz0lTKWdSRYxbe3oGrU28q', 0),
(34, 6, '$2y$10$wdD5FbjqhkM/VhBD.v6jduSiNfZsaJHBD5iNBEzlZzrONyFGdYUrW', 0),
(35, 6, '$2y$10$YeHPA25J/8sT9CwGvLq/vOyZKpJndfbg/D3d2zbwR3RqmvLXWM3uK', 0),
(36, 6, '$2y$10$lBoD3RmfyuqZ8NkPTRsfEuh7AQgKSuoJwk08iZSf72qrOH9vzhZhK', 0),
(37, 6, '$2y$10$0SwlOS2M.M3J.JxbE6UbP.90606hEJXp0RUVYNHNwR9So8lDU9suW', 0),
(38, 6, '$2y$10$4rIuAAv9ULaq8hZiKRZ7HO/kjDY/Xeb1XAQahy6wArszaarOIrKr6', 0),
(39, 6, '$2y$10$SoIIcbkeglBQ7pf4RGfsFOrEnA1buRxAx4Fv3FitdTeyUKJkhjhwe', 0),
(40, 6, '$2y$10$JFuDCfFLBlJBLs8ZhgV8Q.hnFQjPshX6I30NGHpmF5nGhCXbYJEIy', 0);

-- --------------------------------------------------------

--
-- Table structure for table `patient`
--

CREATE TABLE `patient` (
  `pid` int(11) NOT NULL,
  `pemail` varchar(255) DEFAULT NULL,
  `pname` varchar(255) DEFAULT NULL,
  `ppassword` varchar(255) DEFAULT NULL,
  `paddress` varchar(255) DEFAULT NULL,
  `pnic` varchar(15) DEFAULT NULL,
  `pdob` date DEFAULT NULL,
  `ptel` varchar(15) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `patient`
--

INSERT INTO `patient` (`pid`, `pemail`, `pname`, `ppassword`, `paddress`, `pnic`, `pdob`, `ptel`) VALUES
(1, 'patient@edoc.com', 'Test Patient', '123', 'Sri Lanka', '0000000000', '2000-01-01', '0120000000'),
(2, 'emhashenudara@gmail.com', 'Hashen Udara', '123', 'Sri Lanka', '0110000000', '2022-06-03', '0700000000'),
(3, 'salaki@hotmail.com', 'Big Mo', '1234', 'nowhere but here', '923802', '1970-01-01', '0739739733'),
(4, 'abi@abi.com', 'Abinanth Senthilkumar', 'test123', 'xxxyyyzzz', '12345', '1995-01-18', '0123445678'),
(5, 'manojmellon11@gmail.com', 'Manoj  Kumar', '2244', 'Clayton ', '212', '2002-08-11', '');

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `scheduleid` int(11) NOT NULL,
  `docid` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `scheduledate` date DEFAULT NULL,
  `scheduletime` time DEFAULT NULL,
  `nop` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`scheduleid`, `docid`, `title`, `scheduledate`, `scheduletime`, `nop`) VALUES
(1, '1', 'Test Session', '2050-01-01', '18:00:00', 50),
(9, '2', 'Andy Consultation', '2027-01-01', '08:10:00', 12);

-- --------------------------------------------------------

--
-- Table structure for table `specialties`
--

CREATE TABLE `specialties` (
  `id` int(11) NOT NULL,
  `sname` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `specialties`
--

INSERT INTO `specialties` (`id`, `sname`) VALUES
(1, 'Accident and emergency medicine'),
(2, 'Allergology'),
(3, 'Anaesthetics'),
(4, 'Biological hematology'),
(5, 'Cardiology'),
(6, 'Child psychiatry'),
(7, 'Clinical biology'),
(8, 'Clinical chemistry'),
(9, 'Clinical neurophysiology'),
(10, 'Clinical radiology'),
(11, 'Dental, oral and maxillo-facial surgery'),
(12, 'Dermato-venerology'),
(13, 'Dermatology'),
(14, 'Endocrinology'),
(15, 'Gastro-enterologic surgery'),
(16, 'Gastroenterology'),
(17, 'General hematology'),
(18, 'General Practice'),
(19, 'General surgery'),
(20, 'Geriatrics'),
(21, 'Immunology'),
(22, 'Infectious diseases'),
(23, 'Internal medicine'),
(24, 'Laboratory medicine'),
(25, 'Maxillo-facial surgery'),
(26, 'Microbiology'),
(27, 'Nephrology'),
(28, 'Neuro-psychiatry'),
(29, 'Neurology'),
(30, 'Neurosurgery'),
(31, 'Nuclear medicine'),
(32, 'Obstetrics and gynecology'),
(33, 'Occupational medicine'),
(34, 'Ophthalmology'),
(35, 'Orthopaedics'),
(36, 'Otorhinolaryngology'),
(37, 'Paediatric surgery'),
(38, 'Paediatrics'),
(39, 'Pathology'),
(40, 'Pharmacology'),
(41, 'Physical medicine and rehabilitation'),
(42, 'Plastic surgery'),
(43, 'Podiatric Medicine'),
(44, 'Podiatric Surgery'),
(45, 'Psychiatry'),
(46, 'Public health and Preventive Medicine'),
(47, 'Radiology'),
(48, 'Radiotherapy'),
(49, 'Respiratory medicine'),
(50, 'Rheumatology'),
(51, 'Stomatology'),
(52, 'Thoracic surgery'),
(53, 'Tropical medicine'),
(54, 'Urology'),
(55, 'Vascular surgery'),
(56, 'Venereology');

-- --------------------------------------------------------

--
-- Table structure for table `webuser`
--

CREATE TABLE `webuser` (
  `email` varchar(255) NOT NULL,
  `usertype` char(1) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `webuser`
--

INSERT INTO `webuser` (`email`, `usertype`) VALUES
('admin@edoc.com', 'a'),
('doctor@edoc.com', 'd'),
('patient@edoc.com', 'p'),
('emhashenudara@gmail.com', 'p'),
('salaki@hotmail.com', 'p'),
('andyhiggens344@gmail.com', 'd'),
('abi@abi.com', 'p'),
('manojmellon11@gmail.com', 'p'),
('Aaanshu@doc.com', 'd'),
('doctor@uu.com', 'd'),
('doctor@aud.com', 'd'),
('doctor@au.com', 'd');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`aemail`);

--
-- Indexes for table `appointment`
--
ALTER TABLE `appointment`
  ADD PRIMARY KEY (`appoid`),
  ADD KEY `pid` (`pid`),
  ADD KEY `scheduleid` (`scheduleid`);

--
-- Indexes for table `doctor`
--
ALTER TABLE `doctor`
  ADD PRIMARY KEY (`docid`),
  ADD KEY `specialties` (`specialties`);

--
-- Indexes for table `doctor_2fa`
--
ALTER TABLE `doctor_2fa`
  ADD PRIMARY KEY (`docid`);

--
-- Indexes for table `doctor_2fa_backup_codes`
--
ALTER TABLE `doctor_2fa_backup_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patient`
--
ALTER TABLE `patient`
  ADD PRIMARY KEY (`pid`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`scheduleid`),
  ADD KEY `docid` (`docid`);

--
-- Indexes for table `specialties`
--
ALTER TABLE `specialties`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `webuser`
--
ALTER TABLE `webuser`
  ADD PRIMARY KEY (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointment`
--
ALTER TABLE `appointment`
  MODIFY `appoid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `doctor`
--
ALTER TABLE `doctor`
  MODIFY `docid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `doctor_2fa_backup_codes`
--
ALTER TABLE `doctor_2fa_backup_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `patient`
--
ALTER TABLE `patient`
  MODIFY `pid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `scheduleid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
