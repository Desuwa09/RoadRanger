-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 18, 2026 at 05:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `roadranger`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_generation_logs`
--

CREATE TABLE `ai_generation_logs` (
  `log_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `module_id` int(11) DEFAULT NULL,
  `prompt_used` text NOT NULL,
  `status` varchar(50) NOT NULL,
  `api_response_time_ms` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `certificate_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `recipient_name` varchar(201) NOT NULL,
  `certificate_code` varchar(100) DEFAULT NULL,
  `issue_date` datetime DEFAULT NULL,
  `email_sent` tinyint(1) DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `game_id` int(11) NOT NULL,
  `game_key` varchar(50) NOT NULL,
  `game_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`game_id`, `game_key`, `game_name`, `description`) VALUES
(1, 'hotspot_test', 'The Test (Hotspot Spotting)', 'Spot dynamic traffic violations and road hazards directly on real-world imagery.'),
(2, 'memory_game', 'Memory Matrix', 'Match road safety signs, traffic symbols, and regulations before the timer runs out.'),
(3, 'conveyor_game', 'Conveyor Mania', 'Sort incoming traffic rules, signs, or compliance laws into correct categories down the belt.');

-- --------------------------------------------------------

--
-- Table structure for table `game_items`
--

CREATE TABLE `game_items` (
  `item_id` int(11) NOT NULL,
  `level_id` int(11) NOT NULL,
  `item_label` varchar(255) NOT NULL,
  `item_image` varchar(255) DEFAULT NULL,
  `shape_type` varchar(20) DEFAULT 'rect',
  `pos_x` decimal(5,2) DEFAULT NULL,
  `pos_y` decimal(5,2) DEFAULT NULL,
  `width` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `target_category` varchar(100) DEFAULT NULL,
  `match_pair_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `game_items`
--

INSERT INTO `game_items` (`item_id`, `level_id`, `item_label`, `item_image`, `shape_type`, `pos_x`, `pos_y`, `width`, `height`, `target_category`, `match_pair_id`) VALUES
(8, 7, 'Violation', NULL, 'circle', 7.54, 25.15, 17.69, 24.65, NULL, NULL),
(9, 8, 'Violation', NULL, 'rect', 9.85, 23.52, 17.85, 27.21, NULL, NULL),
(10, 9, 'Violation', NULL, 'rect', 9.85, 23.52, 17.85, 27.21, NULL, NULL),
(11, 10, 'Illegal Parking', NULL, 'rect', 6.42, 5.84, 47.68, 37.38, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `game_levels`
--

CREATE TABLE `game_levels` (
  `level_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `difficulty` varchar(20) NOT NULL DEFAULT 'easy',
  `background_image` varchar(255) DEFAULT NULL,
  `time_limit_seconds` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `game_levels`
--

INSERT INTO `game_levels` (`level_id`, `game_id`, `title`, `description`, `difficulty`, `background_image`, `time_limit_seconds`, `created_at`) VALUES
(7, 1, 'Illegal Parking', 'click on illegally parked cars', 'easy', '../../assets/imgs/Scenarios/scenario_6a190d1d81e907.11201271.jpg', 0, '2026-05-29 03:50:53'),
(8, 1, 'Illegal Parking', '', 'easy', '../../assets/imgs/Scenarios/scenario_6a1a342472a288.87217401.jpg', 0, '2026-05-30 00:49:40'),
(9, 1, 'Illegal Parking', '', 'easy', '../../assets/imgs/Scenarios/scenario_6a1a342801fd66.55188401.jpg', 0, '2026-05-30 00:49:44'),
(10, 1, 'Random', 'Random', 'easy', '../../assets/imgs/Scenarios/scenario_6a5aeb749605b3.81027932.png', 0, '2026-07-18 02:56:52');

-- --------------------------------------------------------

--
-- Table structure for table `learning_modules`
--

CREATE TABLE `learning_modules` (
  `module_id` int(11) NOT NULL,
  `chapter_number` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `module_data` longtext NOT NULL,
  `certificate_template` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `learning_modules`
--

INSERT INTO `learning_modules` (`module_id`, `chapter_number`, `title`, `description`, `module_data`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 1, 'FDM-Vol.1-2nd Edition', 'Land Transportation Module', '{\"en\":{\"nodes\":{\"start\":{\"bot_message\":\"Welcome to RoadRangers! Let\'s test your knowledge on Philippine Land Transportation rules. We\'ll start with scenarios related to Conductor\'s Licenses.\",\"choices\":[{\"text\":\"Start Quiz: Conductor\'s License\",\"next_node\":\"cl_q1\",\"score_impact\":0}]},\"cl_q1\":{\"bot_message\":\"You\'re a Conductor on a bus involved in a road crash, and you are NOT hurt. What is your immediate action?\",\"choices\":[{\"text\":\"Assist the injured passengers and call for help.\",\"next_node\":\"cl_q1_feedback_correct\",\"score_impact\":10},{\"text\":\"Wait for law enforcement to arrive before doing anything.\",\"next_node\":\"cl_q1_feedback_inc1\",\"score_impact\":0},{\"text\":\"Check for damage to the bus first.\",\"next_node\":\"cl_q1_feedback_inc2\",\"score_impact\":0},{\"text\":\"Tell passengers to exit the bus immediately, regardless of injuries.\",\"next_node\":\"cl_q1_feedback_inc3\",\"score_impact\":0}]},\"cl_q1_feedback_correct\":{\"bot_message\":\"Correct! Your priority is the safety and well-being of the passengers. Assisting the injured and calling for help are crucial first steps. Let\'s move to the next question.\",\"choices\":[{\"text\":\"Next Question\",\"next_node\":\"cl_q2\",\"score_impact\":0}]},\"cl_q1_feedback_inc1\":{\"bot_message\":\"Not quite. While waiting for authorities is part of the process, your immediate duty is to assist passengers and ensure help is on the way.\",\"choices\":[{\"text\":\"Next Question\",\"next_node\":\"cl_q2\",\"score_impact\":0}]},\"cl_q1_feedback_inc2\":{\"bot_message\":\"Passenger safety comes first. While damage assessment is important, it should not precede assisting the injured. Always prioritize human life.\",\"choices\":[{\"text\":\"Next Question\",\"next_node\":\"cl_q2\",\"score_impact\":0}]},\"cl_q1_feedback_inc3\":{\"bot_message\":\"Evacuation might be necessary, but your first step is to assess injuries and call for assistance, ensuring a safe and controlled environment before directing movement.\",\"choices\":[{\"text\":\"Next Question\",\"next_node\":\"cl_q2\",\"score_impact\":0}]},\"cl_q2\":{\"bot_message\":\"How much is the fare discount granted to senior citizens, persons with disability (PWDs), and students pursuant to R.A. No. 9994, R.A. No. 9442, and R.A. No. 11314?\",\"choices\":[{\"text\":\"10% discount\",\"next_node\":\"cl_q2_feedback_inc1\",\"score_impact\":0},{\"text\":\"15% discount\",\"next_node\":\"cl_q2_feedback_inc2\",\"score_impact\":0},{\"text\":\"20% discount of the prescribed fare\",\"next_node\":\"cl_q2_feedback_correct\",\"score_impact\":10},{\"text\":\"25% discount\",\"next_node\":\"cl_q2_feedback_inc3\",\"score_impact\":0}]},\"cl_q2_feedback_correct\":{\"bot_message\":\"That\'s right! Senior citizens, PWDs, and students are entitled to a 20% discount on the prescribed fare. Now, let\'s look at motorcycle rules.\",\"choices\":[{\"text\":\"Proceed to Motorcycle Rules\",\"next_node\":\"npm_q1\",\"score_impact\":0}]},\"cl_q2_feedback_inc1\":{\"bot_message\":\"Not quite. The correct discount mandated by law is 20% of the prescribed fare for these groups. Let\'s move on to motorcycle rules.\",\"choices\":[{\"text\":\"Proceed to Motorcycle Rules\",\"next_node\":\"npm_q1\",\"score_impact\":0}]},\"cl_q2_feedback_inc2\":{\"bot_message\":\"Incorrect. The law provides for a 20% discount for senior citizens, PWDs, and students. Let\'s learn about motorcycle rules.\",\"choices\":[{\"text\":\"Proceed to Motorcycle Rules\",\"next_node\":\"npm_q1\",\"score_impact\":0}]},\"cl_q2_feedback_inc3\":{\"bot_message\":\"That\'s higher than the actual discount. The correct fare discount is 20% for senior citizens, PWDs, and students. Let\'s continue to motorcycle rules.\",\"choices\":[{\"text\":\"Proceed to Motorcycle Rules\",\"next_node\":\"npm_q1\",\"score_impact\":0}]},\"npm_q1\":{\"bot_message\":\"You are on a long motorcycle drive and start feeling tired or sleepy. What should you do?\",\"choices\":[{\"text\":\"Push through to your destination, as it\'s probably close.\",\"next_node\":\"npm_q1_feedback_inc1\",\"score_impact\":0},{\"text\":\"Park at an appropriate rest stop (e.g., gasoline station) and take a few minutes nap.\",\"next_node\":\"npm_q1_feedback_correct\",\"score_impact\":10},{\"text\":\"Drink coffee or energy drinks while riding to stay awake.\",\"next_node\":\"npm_q1_feedback_inc2\",\"score_impact\":0},{\"text\":\"Increase your speed to reach your destination faster.\",\"next_node\":\"npm_q1_feedback_inc3\",\"score_impact\":0}]},\"npm_q1_feedback_correct\":{\"bot_message\":\"Excellent! Prioritizing rest is crucial for safety on long drives. Fatigue significantly impairs driving ability. Next up: Light Vehicle rules.\",\"choices\":[{\"text\":\"Proceed to Light Vehicle Rules\",\"next_node\":\"npl_q1\",\"score_impact\":0}]},\"npm_q1_feedback_inc1\":{\"bot_message\":\"That\'s a dangerous choice. Driving while tired or sleepy greatly increases the risk of accidents. Always stop and rest. Let\'s look at Light Vehicle rules.\",\"choices\":[{\"text\":\"Proceed to Light Vehicle Rules\",\"next_node\":\"npl_q1\",\"score_impact\":0}]},\"npm_q1_feedback_inc2\":{\"bot_message\":\"While stimulants might help short-term, they don\'t replace actual rest and can lead to a \'crash\' later. It\'s best to pull over and nap. Let\'s learn about Light Vehicle rules.\",\"choices\":[{\"text\":\"Proceed to Light Vehicle Rules\",\"next_node\":\"npl_q1\",\"score_impact\":0}]},\"npm_q1_feedback_inc3\":{\"bot_message\":\"Definitely not! Increasing speed while fatigued is extremely dangerous and drastically raises accident risk. Always stop and rest. Moving on to Light Vehicle rules.\",\"choices\":[{\"text\":\"Proceed to Light Vehicle Rules\",\"next_node\":\"npl_q1\",\"score_impact\":0}]},\"npl_q1\":{\"bot_message\":\"When parking downhill on a road without a curb, where should you turn your wheels?\",\"choices\":[{\"text\":\"Toward the center of the road.\",\"next_node\":\"npl_q1_feedback_inc1\",\"score_impact\":0},{\"text\":\"Away from the road edge.\",\"next_node\":\"npl_q1_feedback_inc2\",\"score_impact\":0},{\"text\":\"Toward the road edge.\",\"next_node\":\"npl_q1_feedback_correct\",\"score_impact\":10},{\"text\":\"Keep them straight.\",\"next_node\":\"npl_q1_feedback_inc3\",\"score_impact\":0}]},\"npl_q1_feedback_correct\":{\"bot_message\":\"Correct! When parking downhill without a curb, turning your wheels toward the road edge ensures that if your brakes fail, the vehicle will roll off the road, not into traffic. Finally, let\'s test your knowledge on Road Traffic Signs.\",\"choices\":[{\"text\":\"Proceed to Road Traffic Signs\",\"next_node\":\"rts_q1\",\"score_impact\":0}]},\"npl_q1_feedback_inc1\":{\"bot_message\":\"That\'s incorrect. Turning wheels toward the center of the road could cause your vehicle to roll into traffic if the brakes fail. The correct action is to turn them toward the road edge. Let\'s proceed to Road Traffic Signs.\",\"choices\":[{\"text\":\"Proceed to Road Traffic Signs\",\"next_node\":\"rts_q1\",\"score_impact\":0}]},\"npl_q1_feedback_inc2\":{\"bot_message\":\"Not quite. Turning them away from the road edge would lead the vehicle into the road if brakes fail. The correct method is to turn them toward the road edge. Let\'s move to Road Traffic Signs.\",\"choices\":[{\"text\":\"Proceed to Road Traffic Signs\",\"next_node\":\"rts_q1\",\"score_impact\":0}]},\"npl_q1_feedback_inc3\":{\"bot_message\":\"Keeping your wheels straight is risky in case of brake failure, as the vehicle could roll into traffic. Always angle your wheels to roll away from the road if possible. The correct way is toward the road edge. Let\'s proceed to Road Traffic Signs.\",\"choices\":[{\"text\":\"Proceed to Road Traffic Signs\",\"next_node\":\"rts_q1\",\"score_impact\":0}]},\"rts_q1\":{\"bot_message\":\"Imagine a road sign showing a black icon of a traffic light, often accompanied by text. What does this sign typically indicate?\",\"choices\":[{\"text\":\"A pedestrian crossing ahead.\",\"next_node\":\"rts_q1_feedback_inc1\",\"score_impact\":0},{\"text\":\"Traffic light ahead.\",\"next_node\":\"rts_q1_feedback_correct\",\"score_impact\":10},{\"text\":\"A roundabout ahead.\",\"next_node\":\"rts_q1_feedback_inc2\",\"score_impact\":0},{\"text\":\"Road construction ahead.\",\"next_node\":\"rts_q1_feedback_inc3\",\"score_impact\":0}]},\"rts_q1_feedback_correct\":{\"bot_message\":\"Exactly! A sign with a traffic light symbol warns drivers of a traffic light ahead, requiring them to prepare to stop or yield. You\'ve completed this section of the quiz!\",\"choices\":[]},\"rts_q1_feedback_inc1\":{\"bot_message\":\"Not quite. While there are signs for pedestrian crossings, a sign with a traffic light symbol specifically indicates a traffic light ahead. You\'ve completed this section of the quiz!\",\"choices\":[]},\"rts_q1_feedback_inc2\":{\"bot_message\":\"Incorrect. A roundabout sign has a specific circular arrow symbol. A traffic light symbol indicates a traffic light ahead. You\'ve completed this section of the quiz!\",\"choices\":[]},\"rts_q1_feedback_inc3\":{\"bot_message\":\"That\'s not it. Road construction signs usually involve symbols of workers or machinery. A traffic light symbol indicates a traffic light ahead. You\'ve completed this section of the quiz!\",\"choices\":[]}}},\"tl\":{\"nodes\":{\"start\":{\"bot_message\":\"Maligayang pagdating sa RoadRangers! Subukan natin ang iyong kaalaman sa mga panuntunan ng Philippine Land Transportation. Magsisimula tayo sa mga sitwasyon na may kaugnayan sa Lisensya ng Konduktor.\",\"choices\":[{\"text\":\"Simulan ang Pagsusulit: Lisensya ng Konduktor\",\"next_node\":\"cl_q1\",\"score_impact\":0}]},\"cl_q1\":{\"bot_message\":\"Ikaw ay isang Konduktor sa isang bus na nasangkot sa isang aksidente sa kalsada, at HINDI ka nasaktan. Ano ang iyong agarang gagawin?\",\"choices\":[{\"text\":\"Tulungan ang mga nasaktang pasahero at tumawag para humingi ng tulong.\",\"next_node\":\"cl_q1_feedback_correct\",\"score_impact\":10},{\"text\":\"Hintayin ang pagdating ng mga tagapagpatupad ng batas bago gumawa ng anuman.\",\"next_node\":\"cl_q1_feedback_inc1\",\"score_impact\":0},{\"text\":\"Suriin muna ang pinsala sa bus.\",\"next_node\":\"cl_q1_feedback_inc2\",\"score_impact\":0},{\"text\":\"Sabihin sa mga pasahero na lumabas agad ng bus, anuman ang kanilang pinsala.\",\"next_node\":\"cl_q1_feedback_inc3\",\"score_impact\":0}]},\"cl_q1_feedback_correct\":{\"bot_message\":\"Tama! Ang iyong prayoridad ay ang kaligtasan at kapakanan ng mga pasahero. Mahalagang unang hakbang ang pagtulong sa mga nasaktan at pagtawag para humingi ng tulong. Dumako tayo sa susunod na tanong.\",\"choices\":[{\"text\":\"Susunod na Tanong\",\"next_node\":\"cl_q2\",\"score_impact\":0}]},\"cl_q1_feedback_inc1\":{\"bot_message\":\"Hindi eksakto. Bagama\'t bahagi ng proseso ang paghihintay sa mga awtoridad, ang iyong agarang tungkulin ay tulungan ang mga pasahero at tiyakin na paparating na ang tulong.\",\"choices\":[{\"text\":\"Susunod na Tanong\",\"next_node\":\"cl_q2\",\"score_impact\":0}]},\"cl_q1_feedback_inc2\":{\"bot_message\":\"Nauuna ang kaligtasan ng pasahero. Bagama\'t mahalaga ang pagtatasa ng pinsala, hindi ito dapat unahin bago tulungan ang mga nasaktan. Laging unahin ang buhay ng tao.\",\"choices\":[{\"text\":\"Susunod na Tanong\",\"next_node\":\"cl_q2\",\"score_impact\":0}]},\"cl_q1_feedback_inc3\":{\"bot_message\":\"Maaaring kinakailangan ang paglikas, ngunit ang iyong unang hakbang ay suriin ang mga pinsala at tumawag para sa tulong, tinitiyak ang isang ligtas at kontroladong kapaligiran bago idirekta ang paggalaw.\",\"choices\":[{\"text\":\"Susunod na Tanong\",\"next_node\":\"cl_q2\",\"score_impact\":0}]},\"cl_q2\":{\"bot_message\":\"Magkano ang diskwento sa pamasahe na ibinibigay sa mga senior citizen, persons with disability (PWDs), at estudyante alinsunod sa R.A. No. 9994, R.A. No. 9442, at R.A. No. 11314?\",\"choices\":[{\"text\":\"10% diskwento\",\"next_node\":\"cl_q2_feedback_inc1\",\"score_impact\":0},{\"text\":\"15% diskwento\",\"next_node\":\"cl_q2_feedback_inc2\",\"score_impact\":0},{\"text\":\"20% diskwento ng itinakdang pamasahe\",\"next_node\":\"cl_q2_feedback_correct\",\"score_impact\":10},{\"text\":\"25% diskwento\",\"next_node\":\"cl_q2_feedback_inc3\",\"score_impact\":0}]},\"cl_q2_feedback_correct\":{\"bot_message\":\"Tama iyan! Ang mga senior citizen, PWD, at estudyante ay may karapatan sa 20% diskwento sa itinakdang pamasahe. Ngayon, tingnan natin ang mga patakaran sa motorsiklo.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Panuntunan sa Motorsiklo\",\"next_node\":\"npm_q1\",\"score_impact\":0}]},\"cl_q2_feedback_inc1\":{\"bot_message\":\"Hindi eksakto. Ang tamang diskwento na ipinag-uutos ng batas ay 20% ng itinakdang pamasahe para sa mga grupong ito. Dumako tayo sa mga patakaran sa motorsiklo.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Panuntunan sa Motorsiklo\",\"next_node\":\"npm_q1\",\"score_impact\":0}]},\"cl_q2_feedback_inc2\":{\"bot_message\":\"Mali. Ang batas ay nagbibigay ng 20% diskwento para sa mga senior citizen, PWDs, at estudyante. Pag-aralan natin ang mga patakaran sa motorsiklo.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Panuntunan sa Motorsiklo\",\"next_node\":\"npm_q1\",\"score_impact\":0}]},\"cl_q2_feedback_inc3\":{\"bot_message\":\"Mas mataas iyan kaysa sa aktwal na diskwento. Ang tamang diskwento sa pamasahe ay 20% para sa mga senior citizen, PWDs, at estudyante. Magpatuloy tayo sa mga patakaran sa motorsiklo.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Panuntunan sa Motorsiklo\",\"next_node\":\"npm_q1\",\"score_impact\":0}]},\"npm_q1\":{\"bot_message\":\"Ikaw ay nasa isang mahabang biyahe ng motorsiklo at nagsisimula nang makaramdam ng pagod o antok. Ano ang dapat mong gawin?\",\"choices\":[{\"text\":\"Ituloy ang biyahe patungo sa iyong destinasyon, dahil malamang ay malapit na ito.\",\"next_node\":\"npm_q1_feedback_inc1\",\"score_impact\":0},{\"text\":\"Iparada sa isang angkop na pahingahan (hal., gasolinahan) at umidlip ng ilang minuto.\",\"next_node\":\"npm_q1_feedback_correct\",\"score_impact\":10},{\"text\":\"Uminom ng kape o energy drinks habang nagmamaneho para manatiling gising.\",\"next_node\":\"npm_q1_feedback_inc2\",\"score_impact\":0},{\"text\":\"Dagdagan ang iyong bilis para mas mabilis na makarating sa iyong destinasyon.\",\"next_node\":\"npm_q1_feedback_inc3\",\"score_impact\":0}]},\"npm_q1_feedback_correct\":{\"bot_message\":\"Mahusay! Mahalaga ang pagbibigay-prayoridad sa pahinga para sa kaligtasan sa mahabang biyahe. Ang pagod ay lubhang nakakapinsala sa kakayahan sa pagmamaneho. Susunod: Mga patakaran para sa Sasakyang Magaan.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Panuntunan sa Sasakyang Magaan\",\"next_node\":\"npl_q1\",\"score_impact\":0}]},\"npm_q1_feedback_inc1\":{\"bot_message\":\"Mapanganib iyan. Ang pagmamaneho habang pagod o inaantok ay lubos na nagpapataas ng panganib ng aksidente. Laging huminto at magpahinga. Tingnan natin ang mga patakaran sa Sasakyang Magaan.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Panuntunan sa Sasakyang Magaan\",\"next_node\":\"npl_q1\",\"score_impact\":0}]},\"npm_q1_feedback_inc2\":{\"bot_message\":\"Bagama\'t maaaring makatulong ang mga pampasigla sa panandalian, hindi nito napapalitan ang tunay na pahinga at maaaring magdulot ng \'crash\' sa bandang huli. Pinakamainam na huminto at umidlip. Alamin natin ang mga patakaran sa Sasakyang Magaan.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Panuntunan sa Sasakyang Magaan\",\"next_node\":\"npl_q1\",\"score_impact\":0}]},\"npm_q1_feedback_inc3\":{\"bot_message\":\"Tiyak na hindi! Ang pagpapabilis habang pagod ay lubhang mapanganib at lubos na nagpapataas ng panganib ng aksidente. Laging huminto at magpahinga. Dumako na tayo sa mga patakaran sa Sasakyang Magaan.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Panuntunan sa Sasakyang Magaan\",\"next_node\":\"npl_q1\",\"score_impact\":0}]},\"npl_q1\":{\"bot_message\":\"Kapag nagpaparking pababa sa isang kalsada na walang bangketa, saan mo dapat iikot ang iyong mga gulong?\",\"choices\":[{\"text\":\"Patungo sa gitna ng kalsada.\",\"next_node\":\"npl_q1_feedback_inc1\",\"score_impact\":0},{\"text\":\"Malayo sa gilid ng kalsada.\",\"next_node\":\"npl_q1_feedback_inc2\",\"score_impact\":0},{\"text\":\"Patungo sa gilid ng kalsada.\",\"next_node\":\"npl_q1_feedback_correct\",\"score_impact\":10},{\"text\":\"Panatilihin itong tuwid.\",\"next_node\":\"npl_q1_feedback_inc3\",\"score_impact\":0}]},\"npl_q1_feedback_correct\":{\"bot_message\":\"Tama! Kapag nagpaparking pababa nang walang bangketa, ang pag-ikot ng iyong mga gulong patungo sa gilid ng kalsada ay nagsisiguro na kung pumalya ang iyong preno, ang sasakyan ay lalabas sa kalsada, hindi sa daloy ng trapiko. Panghuli, subukan natin ang iyong kaalaman sa Mga Senyales ng Trapiko sa Kalsada.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Senyales ng Trapiko sa Kalsada\",\"next_node\":\"rts_q1\",\"score_impact\":0}]},\"npl_q1_feedback_inc1\":{\"bot_message\":\"Mali iyan. Ang pag-ikot ng mga gulong patungo sa gitna ng kalsada ay maaaring maging sanhi upang gumulong ang iyong sasakyan patungo sa daloy ng trapiko kung pumalya ang preno. Ang tamang aksyon ay iikot ang mga ito patungo sa gilid ng kalsada. Dumako tayo sa Mga Senyales ng Trapiko sa Kalsada.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Senyales ng Trapiko sa Kalsada\",\"next_node\":\"rts_q1\",\"score_impact\":0}]},\"npl_q1_feedback_inc2\":{\"bot_message\":\"Hindi eksakto. Ang pag-ikot ng mga ito palayo sa gilid ng kalsada ay magtutulak sa sasakyan patungo sa kalsada kung pumalya ang preno. Ang tamang paraan ay iikot ang mga ito patungo sa gilid ng kalsada. Dumako tayo sa Mga Senyales ng Trapiko sa Kalsada.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Senyales ng Trapiko sa Kalsada\",\"next_node\":\"rts_q1\",\"score_impact\":0}]},\"npl_q1_feedback_inc3\":{\"bot_message\":\"Mapanganib ang pananatili ng iyong mga gulong na tuwid kung sakaling pumalya ang preno, dahil maaaring gumulong ang sasakyan patungo sa daloy ng trapiko. Laging itulak ang iyong mga gulong upang lumayo sa kalsada kung posible. Ang tamang paraan ay patungo sa gilid ng kalsada. Dumako tayo sa Mga Senyales ng Trapiko sa Kalsada.\",\"choices\":[{\"text\":\"Magpatuloy sa Mga Senyales ng Trapiko sa Kalsada\",\"next_node\":\"rts_q1\",\"score_impact\":0}]},\"rts_q1\":{\"bot_message\":\"Isipin ang isang karatula sa kalsada na nagpapakita ng itim na icon ng traffic light, na madalas may kasamang teksto. Ano ang karaniwang ipinahihiwatig ng karatula na ito?\",\"choices\":[{\"text\":\"Isang tawiran ng pedestrian sa unahan.\",\"next_node\":\"rts_q1_feedback_inc1\",\"score_impact\":0},{\"text\":\"Traffic light sa unahan.\",\"next_node\":\"rts_q1_feedback_correct\",\"score_impact\":10},{\"text\":\"Isang rotonda sa unahan.\",\"next_node\":\"rts_q1_feedback_inc2\",\"score_impact\":0},{\"text\":\"May konstruksyon ng kalsada sa unahan.\",\"next_node\":\"rts_q1_feedback_inc3\",\"score_impact\":0}]},\"rts_q1_feedback_correct\":{\"bot_message\":\"Tama! Ang isang karatula na may simbolo ng traffic light ay nagbababala sa mga driver na may traffic light sa unahan, na nangangailangan sa kanila na maghanda upang huminto o magbigay-daan. Nakumpleto mo na ang seksyon na ito ng pagsusulit!\",\"choices\":[]},\"rts_q1_feedback_inc1\":{\"bot_message\":\"Hindi eksakto. Bagama\'t may mga karatula para sa tawiran ng pedestrian, ang isang karatula na may simbolo ng traffic light ay partikular na nagpapahiwatig ng isang traffic light sa unahan. Nakumpleto mo na ang seksyon na ito ng pagsusulit!\",\"choices\":[]},\"rts_q1_feedback_inc2\":{\"bot_message\":\"Mali. Ang karatula ng rotonda ay may partikular na bilog na simbolo ng arrow. Ang simbolo ng traffic light ay nagpapahiwatig ng traffic light sa unahan. Nakumpleto mo na ang seksyon na ito ng pagsusulit!\",\"choices\":[]},\"rts_q1_feedback_inc3\":{\"bot_message\":\"Hindi iyan. Ang mga karatula ng konstruksyon ng kalsada ay karaniwang may kasamang simbolo ng mga manggagawa o makinarya. Ang simbolo ng traffic light ay nagpapahiwatig ng traffic light sa unahan. Nakumpleto mo na ang seksyon na ito ng pagsusulit!\",\"choices\":[]}}}}', 1, '2026-05-30 00:49:16', '2026-05-30 00:49:16');

-- --------------------------------------------------------

--
-- Table structure for table `progress`
--

CREATE TABLE `progress` (
  `progress_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `game_name` varchar(100) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `stage_number` int(11) DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT NULL,
  `progress_percent` decimal(5,2) DEFAULT NULL,
  `completion_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `progress`
--

INSERT INTO `progress` (`progress_id`, `user_id`, `game_name`, `module_id`, `stage_number`, `is_completed`, `progress_percent`, `completion_date`) VALUES
(1, 2, 'memory_game', NULL, 1, 0, 3.00, '2026-05-28 00:59:22'),
(2, 2, 'conveyor_mania', NULL, 1, 1, 100.00, '2026-05-28 00:29:51'),
(3, 2, 'conveyor_mania', NULL, 0, 1, 100.00, '2026-05-28 00:29:51'),
(4, 2, 'hotspot_test', 3, 8, 1, 100.00, '2026-05-30 02:50:05'),
(5, 2, 'hotspot_test', 3, 10, 1, 100.00, '2026-07-18 04:57:13');

-- --------------------------------------------------------

--
-- Table structure for table `scores`
--

CREATE TABLE `scores` (
  `score_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `game_name` varchar(100) DEFAULT NULL,
  `module_id` int(11) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `attempts` int(11) DEFAULT NULL,
  `date_taken` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scores`
--

INSERT INTO `scores` (`score_id`, `user_id`, `game_name`, `module_id`, `score`, `attempts`, `date_taken`) VALUES
(10, 2, 'hotspot_test', NULL, 10, NULL, '2026-05-29 11:21:31'),
(11, 2, 'hotspot_test', NULL, 10, NULL, '2026-05-29 11:36:33'),
(12, 2, 'hotspot_test', NULL, 10, NULL, '2026-05-29 11:51:09'),
(13, 2, 'hotspot_test', NULL, 10, NULL, '2026-05-30 07:26:37'),
(14, 2, 'hotspot_test', NULL, 10, NULL, '2026-05-30 08:50:05'),
(15, 2, 'hotspot_test', NULL, 10, NULL, '2026-07-18 10:57:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `phone` varchar(11) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `created_at` date NOT NULL,
  `status` varchar(50) NOT NULL,
  `birthday` date DEFAULT NULL,
  `age_group` varchar(20) DEFAULT 'college_adult',
  `current_difficulty` varchar(10) DEFAULT 'hard',
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `last_active` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `oauth_provider` varchar(50) DEFAULT NULL,
  `is_email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Admin security feature',
  `login_attempts` int(11) NOT NULL DEFAULT 0,
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `account_locked_until` timestamp NULL DEFAULT NULL,
  `login_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `username`, `email`, `password`, `gender`, `phone`, `google_id`, `created_at`, `status`, `birthday`, `age_group`, `current_difficulty`, `is_admin`, `last_active`, `oauth_provider`, `is_email_verified`, `two_factor_enabled`, `login_attempts`, `last_failed_login`, `account_locked_until`, `login_token`, `token_expires_at`) VALUES
(1, 'Admin', 'Account', 'admin_test', 'admin@roadranger.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, NULL, '2026-05-05', 'active', NULL, 'college_adult', 'hard', 1, '2026-05-27 23:01:31', NULL, 0, 0, 0, NULL, NULL, NULL, NULL),
(2, 'Clarenz', 'Geronimo', 'clrnzvncntgeronimo@gmail.com', 'clrnzvncntgeronimo@gmail.com', '$2y$10$53j5wBvwnjz7TwHQ5YAh3utcQVuq3yv2t5/0rAt0tPazgvoGbFpyK', 'male', '090563711', NULL, '2026-05-04', 'active', '2005-01-11', 'college_adult', 'hard', 0, '2026-07-18 02:57:40', NULL, 0, 0, 0, NULL, NULL, '06172327c8e30e28d72457d5240e2cd9dfaf7ed1f925550fe9eedcd0b771cbc0', '2026-06-03 03:45:22'),
(9, 'Justine', 'Reyes', 'reyesjustine@gmail.com', 'reyesjustine@gmail.com', '$2y$10$LaGf3XRaCS4kWEzdjBg9qumJz..bx42e1kqVSRsWCfydTkyySfuUq', 'Male', '09224810891', NULL, '2026-05-28', 'active', '2014-01-05', 'highschool', 'medium', 0, '2026-05-27 23:55:48', NULL, 0, 0, 0, NULL, NULL, NULL, NULL),
(10, 'Vincent', 'Geronimo', 'Vincentgeronimo@gmail.com', 'Vincentgeronimo@gmail.com', '$2y$10$Nm/077jb9LbYHiXpFuBq9uNmfn35TS9HWJXqD72ZhqOddgVaR/9TK', 'Male', '09056371139', NULL, '2026-05-28', 'active', '1979-01-11', 'college_adult', 'hard', 0, '2026-05-28 00:46:58', NULL, 0, 0, 0, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_generation_logs`
--
ALTER TABLE `ai_generation_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_logs_admin` (`admin_id`),
  ADD KEY `fk_logs_module` (`module_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`certificate_id`),
  ADD UNIQUE KEY `uq_certificates_user_module` (`user_id`,`module_id`),
  ADD KEY `fk_certificates_user` (`user_id`),
  ADD KEY `fk_certificates_module` (`module_id`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`game_id`),
  ADD UNIQUE KEY `game_key` (`game_key`);

--
-- Indexes for table `game_items`
--
ALTER TABLE `game_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_items_level` (`level_id`);

--
-- Indexes for table `game_levels`
--
ALTER TABLE `game_levels`
  ADD PRIMARY KEY (`level_id`),
  ADD KEY `fk_levels_game` (`game_id`);

--
-- Indexes for table `learning_modules`
--
ALTER TABLE `learning_modules`
  ADD PRIMARY KEY (`module_id`),
  ADD KEY `fk_modules_admin` (`created_by`);

--
-- Indexes for table `progress`
--
ALTER TABLE `progress`
  ADD PRIMARY KEY (`progress_id`),
  ADD KEY `fk_progress_user` (`user_id`),
  ADD KEY `fk_progress_module` (`module_id`);

--
-- Indexes for table `scores`
--
ALTER TABLE `scores`
  ADD PRIMARY KEY (`score_id`),
  ADD KEY `fk_scores_user` (`user_id`),
  ADD KEY `fk_scores_module` (`module_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `google_id` (`google_id`),
  ADD KEY `idx_is_admin_status` (`is_admin`,`status`),
  ADD KEY `idx_login_attempts` (`login_attempts`,`account_locked_until`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_generation_logs`
--
ALTER TABLE `ai_generation_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `certificate_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `game_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `game_items`
--
ALTER TABLE `game_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `game_levels`
--
ALTER TABLE `game_levels`
  MODIFY `level_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `learning_modules`
--
ALTER TABLE `learning_modules`
  MODIFY `module_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `progress`
--
ALTER TABLE `progress`
  MODIFY `progress_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `scores`
--
ALTER TABLE `scores`
  MODIFY `score_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_generation_logs`
--
ALTER TABLE `ai_generation_logs`
  ADD CONSTRAINT `fk_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_logs_module` FOREIGN KEY (`module_id`) REFERENCES `learning_modules` (`module_id`) ON DELETE SET NULL;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `fk_certificates_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_certificates_module` FOREIGN KEY (`module_id`) REFERENCES `learning_modules` (`module_id`) ON DELETE CASCADE;

--
-- Constraints for table `game_items`
--
ALTER TABLE `game_items`
  ADD CONSTRAINT `fk_items_level` FOREIGN KEY (`level_id`) REFERENCES `game_levels` (`level_id`) ON DELETE CASCADE;

--
-- Constraints for table `game_levels`
--
ALTER TABLE `game_levels`
  ADD CONSTRAINT `fk_levels_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`game_id`) ON DELETE CASCADE;

--
-- Constraints for table `learning_modules`
--
ALTER TABLE `learning_modules`
  ADD CONSTRAINT `fk_modules_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `progress`
--
ALTER TABLE `progress`
  ADD CONSTRAINT `fk_progress_module` FOREIGN KEY (`module_id`) REFERENCES `learning_modules` (`module_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `scores`
--
ALTER TABLE `scores`
  ADD CONSTRAINT `fk_scores_module` FOREIGN KEY (`module_id`) REFERENCES `learning_modules` (`module_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scores_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
