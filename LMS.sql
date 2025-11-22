-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 22, 2025 at 05:59 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `LMS`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_login`
--

CREATE TABLE `admin_login` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_login`
--

INSERT INTO `admin_login` (`id`, `username`, `email`, `password`, `created_at`) VALUES
(1, 'LMS', 'lms@123.com', '$2y$10$qzxhfKR7sxJXg6zvD7z7i.b0lhvsJBBc371Bc6RBr42wV2.eIKTdO', '2024-12-09 07:25:53');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `isbn` varchar(50) NOT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `available_copies` int(11) DEFAULT 0,
  `views` int(11) DEFAULT 0,
  `publisher` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `genre`, `year`, `description`, `image_path`, `file_path`, `created_at`, `available_copies`, `views`, `publisher`) VALUES
(12, 'RICH DAD POOR DAD ', 'ROBERT T.KIYOSAKI', '9781612680019', 'Business', 2010, 'RICH DAD POOR DAD ', 'uploads/images/RICH DAD POOR DAD 86491.jpg', 'uploads/files/RICH DAD POOR DAD 86491.pdf', '2024-12-11 05:35:43', 6, 0, NULL),
(13, 'Things l Wish l\'d KnownBefore We Got Married ', 'GARY CHAPMAN ', '00002', 'Health', 2010, 'GARY CHAPMAN Author Things l Wish l\'d KnownBefore We Got Married ', 'uploads/images/GARY CHAPMAN copy.jpg', 'uploads/files/Things l Wish l\'d KnownBefore We Got Married .pdf', '2024-12-11 05:37:24', 3, 1, NULL),
(14, 'THE POWER OFSELF-DISCIPLINE NO EXCUSES!', 'BRIAN TRACY', '00003', 'Health ', 2010, 'THE POWER OFSELF-DISCIPLINE NO EXCUSES! BRIAN TRACY', 'uploads/images/THE POWER OFSELF-DISCIPLINE NO EXCUSES! BRIAN TRACY.jpg', 'uploads/files/THE POWER OFSELF-DISCIPLINE NO EXCUSES! BRIAN TRACY.pdf', '2024-12-11 05:43:55', 0, 0, NULL),
(18, 'UNDERSTANDING the Purpose and Power of a book for men and the women who love them', 'DR, MYLES MUNROE', '00007', 'Health ', 2010, 'UNDERSTANDING the Purpose and Power of a book for men and the women who love them DR, MYLES MUNROE', 'uploads/images/UNDERSTANDING the Purpose and Power of a book for men and the women who love them DR, MYLES MUNROE.jpg', 'uploads/files/UNDERSTANDING the Purpose and Power of a book for men and the women who love them DR, MYLES MUNROE.pdf', '2024-12-11 05:52:00', 6, 0, NULL),
(19, 'The monk who sold his ferrari', 'Robin sharma ', '00008', 'Health ', 2010, 'The monk who sold his ferrari-robin sharma ', 'uploads/images/The monk who sold his ferrari-robin sharma .jpg', 'uploads/files/The monk who sold his ferrari-robin sharma .pdf', '2024-12-11 05:53:34', 5, 1, NULL),
(20, 'The 5 AM Club ', 'Robin Sharma', '00009', 'Health ', 2010, 'The 5 AM Club - Robin Sharma', 'uploads/images/The 5 AM Club - Robin Sharma.jpg', 'uploads/files/The 5 AM Club - Robin Sharma.pdf', '2024-12-11 05:55:00', 8, 0, NULL),
(21, 'Dopamine-Detox-Book', 'THIBAUT MEURISSE', '00010', 'Health ', 2010, 'Dopamine-Detox-Book', 'uploads/images/Dopamine-Detox-Book.jpg', 'uploads/files/Dopamine-Detox-Book.pdf', '2024-12-11 05:57:28', 7, 0, NULL),
(22, 'C How to Program with an introduction t o C++ EIGHTH EDITION ', ' Paul Deitel', '00011', 'Education', 2010, 'C How to Program with an introduction t o C++ EIGHTH EDITION Paul Deitel', 'uploads/images/C How to Program with an introduction t o C++ EIGHTH EDITION Paul Deitel.jpg', 'uploads/files/C How to Program with an introduction t o C++ EIGHTH EDITION Paul Deitel.pdf', '2024-12-11 06:34:51', 7, 0, NULL),
(23, 'Programming in C ', ' Stephen G. Kochan', '00012', 'Education', 2010, 'Programming in C Stephen G. Kochan', 'uploads/images/Programming in C Stephen G. Kochan.jpg', 'uploads/files/Programming in C Stephen G. Kochan.pdf', '2024-12-11 06:38:25', 6, 0, NULL),
(24, 'Computer Science An Overview (12th Edition)]', 'J. Glenn Brookshear and Dennis Brylow ', '00013', 'Education', 2010, 'Computer Science An Overview (12th Edition) J. Glenn Brookshear and Dennis Brylow ', 'uploads/images/Computer Science An Overview (12th Edition) J. Glenn Brookshear and Dennis Brylow .jpg', 'uploads/files/Computer Science An Overview (12th Edition) J. Glenn Brookshear and Dennis Brylow .pdf', '2024-12-11 06:42:30', 7, 2, NULL),
(25, 'Data Structures and Algorithm Analysis in java', 'MARK ALLEN WEISS', '00014', 'Education', 2010, 'Data Structures and Algorithm Analysis in java MARK ALLEN WEISS', 'uploads/images/Data Structures and Algorithm Analysis in java MARK ALLEN WEISS.jpg', 'uploads/files/Data Structures and Algorithm Analysis in java MARK ALLEN WEISS.pdf', '2024-12-11 06:46:19', 6, 0, NULL),
(26, 'COMPUTER NETWORKS', 'TANENBAUM WETHERALL', '00015', 'Education', 2010, 'COMPUTER NETWORKS TANENBAUM WETHERALL', 'uploads/images/COMPUTER NETWORKS TANENBAUM WETHERALL.jpg', 'uploads/files/COMPUTER NETWORKS TANENBAUM WETHERALL.pdf', '2024-12-11 06:48:40', 6, 3, NULL),
(27, 'Modern Operating System ', 'ANDREW S.TANENBAUM HERBERT', '00016', 'Education', 2010, 'Modern Operating System ANDREW S.TANENBAUM HERBERT', 'uploads/images/Modern Operating System ANDREW S.TANENBAUM HERBERT.jpg', 'uploads/files/Modern Operating System ANDREW S.TANENBAUM HERBERT.pdf', '2024-12-11 06:52:48', 6, 0, NULL),
(28, 'Software Architecture Design Patterns in Java ', 'Partha Kuchana', '00017', 'Education', 2010, 'Software Architecture Design Patterns in Java Partha Kuchana', 'uploads/images/Software Architecture Design Patterns in Java Partha Kuchana.jpg', 'uploads/files/Software Architecture Design Patterns in Java Partha Kuchana.pdf', '2024-12-11 06:55:15', 6, 0, NULL),
(29, 'DOMAIN-SPECIFIC LANGUAGE & SUPPORT TOOLS FOR HIGH-LEVEL STREAM PARALLELISM', 'DALVAN GRIEBLER', '00018', 'Education', 2010, 'DOMAIN-SPECIFIC LANGUAGE & SUPPORT TOOLS FOR HIGH-LEVEL STREAM PARALLELISM DALVAN GRIEBLER', 'uploads/images/DOMAIN-SPECIFIC LANGUAGE & SUPPORT TOOLS FOR HIGH-LEVEL STREAM PARALLELISM DALVAN GRIEBLER.jpg', 'uploads/files/DOMAIN-SPECIFIC LANGUAGE & SUPPORT TOOLS FOR HIGH-LEVEL STREAM PARALLELISM DALVAN GRIEBLER.pdf', '2024-12-11 06:58:55', 6, 0, NULL),
(30, 'Software.Engineering 8th (AW,.China,.2007)', 'Sommerville', '00019', 'Education', 2010, 'Software.Engineering 8th (AW,.China,.2007)Sommerville', 'uploads/images/Software.Engineering 8th (AW,.China,.2007)Sommerville.jpg', 'uploads/files/Software.Engineering 8th (AW,.China,.2007)Sommerville.pdf', '2024-12-11 07:01:05', 6, 0, NULL),
(31, 'Head First Android Development- ', 'Brain', '00020', 'Education', 2010, 'Head First Android Development- A Brain-Friendly Guide', 'uploads/images/Head First Android Development- A Brain-Friendly Guide.jpg', 'uploads/files/Head First Android Development- A Brain-Friendly Guide.jpg', '2024-12-11 07:04:46', 6, 0, NULL),
(32, 'Pro WPF 4.5 C# Windows Presentation Foundation in .NET 4.5', 'Matthew MacDonald ', '00021', 'Education', 2010, 'Pro WPF 4.5 C# Windows Presentation Foundation in .NET 4.5Matthew MacDonald C-, 4th Edition', 'uploads/images/Pro WPF 4.5 C# Windows Presentation Foundation in .NET 4.5Matthew MacDonald.jpg', 'uploads/files/Pro WPF 4.5 C# Windows Presentation Foundation in .NET 4.5Matthew MacDonald C-, 4th Edition.pdf', '2024-12-11 08:03:09', 6, 0, NULL),
(33, 'Calculus for Business ', 'RAYMOND A. BARNETT', '00022', 'Education', 2010, 'Calculus for Business RAYMOND A. BARNETT Merritt College MICHAEL R. ZIEGLER Marquette University KARL E. BYLEEN', 'uploads/images/The Power of Habit\' by Charles Duhigg.jpg', 'uploads/files/Calculus for Business RAYMOND A. BARNETT Merritt College MICHAEL R. ZIEGLER Marquette University KARL E. BYLEEN.pdf', '2024-12-11 08:06:32', 7, 0, NULL),
(34, 'New Perspectives on Computer Concepts 2016 Comprehensive ', 'June Jamrich Parsons', '00023', 'Education', 2010, 'New Perspectives on Computer Concepts 2016 Comprehensive June Jamrich Parsons', 'uploads/images/Untitled-1 copy.jpg', 'uploads/files/Untitled-1 copy.jpg', '2024-12-11 08:10:51', 6, 0, NULL),
(35, 'Engineering-fluid-mechanics', 'Prof.T.T. Al-Shemmeri', '00024', 'Education', 2010, 'engineering-fluid-mechanics-solution-manual Prof.T.T. Al-Shemmeri', 'uploads/images/engineering-fluid-mechanics-solution-manual Prof.T.T. Al-Shemmeri.jpg', 'uploads/files/engineering-fluid-mechanics-solution-manual Prof.T.T. Al-Shemmeri.pdf', '2024-12-11 08:16:25', 6, 0, NULL),
(36, 'Bridge engineering handbook ', 'W.F. Chen and L. Duan', '00025', 'Education', 2010, 'Bridge engineering handbook by W.F. Chen and L. Duan (1998)', 'uploads/images/Bridge engineering handbook by W.F. Chen and L. Duan (1998).jpg', 'uploads/files/Bridge engineering handbook by W.F. Chen and L. Duan (1998).jpg', '2024-12-11 08:20:38', 7, 4, NULL),
(37, 'Bridge Engineering_ Rehabilitation, and Maintenance of Modern Highway Bridges', 'McGraw-Hill, Demetrios Tonias, Jim Zhao', '00026', 'Education', 2010, 'Demetrios Tonias, Jim Zhao-Bridge Engineering_ Rehabilitation, and Maintenance of Modern Highway Bridges-McGraw-Hill Professional (2006)', '0', 'uploads/files/Demetrios Tonias, Jim Zhao-Bridge Engineering_ Rehabilitation, and Maintenance of Modern Highway Bridges-McGraw-Hill Professional (2006).pdf', '2024-12-11 08:24:20', 6, 0, NULL),
(38, 'AUTOCAD2011 ', 'David Byrnes', '00027', 'Education', 2010, 'AUTOCAD2011 David Byrnes', 'uploads/images/AUTOCAD2011 David Byrnes.jpg', 'uploads/files/AUTOCAD2011 David Byrnes.pdf', '2024-12-11 08:27:56', 7, 0, NULL),
(39, 'Linear Algebra. Algorithms, Applications, and Techniques 3rd ed [2014]', 'Richard Bronson', '00028', 'Education', 2010, 'Linear Algebra. Algorithms, Applications, and Techniques 3rd ed [2014]', 'uploads/images/Linear Algebra. Algorithms, Applications, and Techniques 3rd ed [2014].jpg', 'uploads/files/Linear Algebra. Algorithms, Applications, and Techniques 3rd ed [2014].pdf', '2024-12-11 08:31:56', 6, 0, NULL),
(40, 'Linear Algebra and Its Applications 3rd ed (1988)', 'GILBERT STRANG', '00029', 'Education', 2010, 'Strang - Linear Algebra and Its Applications 3rd ed (1988)GIERTANG', 'uploads/images/Linear Algebra. Algorithms, Applications, and Techniques 3rd ed [2014].jpg', 'uploads/files/Linear Algebra. Algorithms, Applications, and Techniques 3rd ed [2014].pdf', '2024-12-11 08:35:52', 6, 0, NULL),
(41, 'Theory of Elasticity', 'Lurie', '00030', 'Education', 2010, 'Lurie-Theory of Elasticity', 'uploads/images/Lurie-Theory of Elasticity.jpg', 'uploads/files/Lurie-Theory of Elasticity.pdf', '2024-12-11 08:38:26', 6, 0, NULL),
(42, 'How to Develop, Train and Use It  -Cosimo Classics (2007)', 'William Walker Atkinson - MEMORY', '00031', 'Health', 2010, 'William Walker Atkinson - MEMORY_ How to Develop, Train and Use It  -Cosimo Classics (2007)', 'uploads/images/IMG_0197.jpg', 'uploads/files/William Walker Atkinson - MEMORY_ How to Develop, Train and Use It  -Cosimo Classics (2007).pdf', '2024-12-11 09:23:40', 6, 0, NULL),
(44, 'Good Morning', 'marvin', '00034', 'Health ', 2010, 'Good Morning', 'uploads/images/IMG_0496.JPG', 'uploads/files/Good Morning, Holy Spirit - Benny Hinn 54877.pdf', '2024-12-11 09:53:30', 8, 0, NULL),
(46, '10-Things-Every-Molecular-Biologist-Should-Know-', 'null', '00035', 'Health', 2013, '10-Things-Every-Molecular-Biologist-Should-Know', 'uploads/images/IMG_0495.JPG', 'uploads/files/10-Things-Every-Molecular-Biologist-Should-Know-1 (1).pdf', '2024-12-19 13:55:03', 2, 0, NULL),
(47, 'NO GRID Survival Projects ', 'Claude Davis Sr., Amber Robinson, Michael Major', '00037', 'Educational', 2015, 'NO GRID Survival Projects (Claude Davis Sr., Amber Robinson, Michael Major) (Z-Library)', 'uploads/images/Lurie-Theory of Elasticity copy.jpg', 'uploads/files/10-Things-Every-Molecular-Biologist-Should-Know-1 (1).pdf', '2025-01-16 07:40:20', 2, 0, NULL),
(48, 'How the mighty fall and why some companies never give in ', 'Collins, James C. (James Charles)', '9780977326419', 'Educational', 1958, 'How the mighty fall - and why some companies never give in - Collins, James C. (James Charles), 1958', 'uploads/images/How the mighty fall - and why some companies never give in - Collins, James C. (James Charles), 1958.jpg', 'uploads/files/JIM_Collins_NBF_Summary_2020_web.pdf', '2025-01-28 04:23:18', 1, 0, NULL),
(49, 'The Great Gatsby', 'F. Scott Fitzgerald', '978-0743273565', 'Classic Literature', 1925, 'A quintessential novel of the Jazz Age, exploring themes of wealth, love, and the American Dream.', 'uploads/images/The Great Gatsby.jpg', 'uploads/files/The Great Gatsby.pdf', '2025-02-18 07:23:41', 5, 150, NULL),
(50, 'To Kill a Mockingbird', 'Harper Lee', '978-0061120084', 'Classic Literature', 1960, 'A powerful story of racial injustice and childhood innocence in the American South.', 'uploads/images/To Kill a Mockingbird.jpg', 'uploads/files/To Kill a Mockingbird.pdf', '2025-02-18 07:23:41', 3, 220, NULL),
(51, '1984', 'George Orwell', '978-0451524935', 'Dystopian Fiction', 1949, 'A chilling vision of a totalitarian society and the dangers of absolute power.', 'uploads/images/1984.jpg', 'uploads/files/1984.pdf', '2025-02-18 07:23:41', 7, 180, NULL),
(52, 'Pride and Prejudice', 'Jane Austen', '978-0141439518', 'Classic Literature', 1813, 'A beloved romantic novel centered on the witty and independent Elizabeth Bennet.', 'uploads/images/Pride and Prejudice.jpg', 'uploads/files/Pride and Prejudice.pdf', '2025-02-18 07:23:41', 4, 195, NULL),
(53, 'The Catcher in the Rye', 'J.D. Salinger', '978-0316769174', 'Literary Fiction', 1951, 'A coming-of-age story narrated by the cynical teenager Holden Caulfield.', 'uploads/images/The Catcher in the Rye.jpg', 'uploads/files/The Catcher in the Rye.pdf', '2025-02-18 07:23:41', 6, 160, NULL),
(54, 'The Lord of the Rings', 'J.R.R. Tolkien', '978-0618260262', 'Fantasy', 1954, 'An epic fantasy adventure in the world of Middle-earth, battling against the Dark Lord Sauron.', 'uploads/images/The Lord of the Rings.jpg', 'uploads/files/The Lord of the Rings.pdf', '2025-02-18 07:23:41', 2, 250, NULL),
(55, 'Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', '978-0590353427', 'Fantasy', 1997, 'The first book in the magical series about a young wizard attending Hogwarts School of Witchcraft and Wizardry.', 'uploads/images/Harry Potter and the Sorcerer\'s Stone.png', 'uploads/files/Harry Potter and the Sorcerer\'s Stone.pdf', '2025-02-18 07:23:41', 8, 300, NULL),
(56, 'The Hobbit', 'J.R.R. Tolkien', '978-0345339683', 'Fantasy', 1937, 'A classic adventure story of Bilbo Baggins on a quest to the Lonely Mountain.', 'uploads/images/The Hobbit.jpg', 'uploads/files/The Hobbit.pdf', '2025-02-18 07:23:41', 5, 210, NULL),
(57, 'The Hitchhiker\'s Guide to the Galaxy', 'Douglas Adams', '978-0345391803', 'Science Fiction', 1979, 'A comedic science fiction series that follows the misadventures of Arthur Dent after Earth is destroyed.', 'uploads/images/The Hitchhiker\'s Guide to the Galaxy.jpg', 'uploads/files/The Hitchhiker\'s Guide to the Galaxy.pdf', '2025-02-18 07:23:41', 9, 170, NULL),
(58, 'Dune', 'Frank Herbert', '978-0441013593', 'Science Fiction', 1965, 'A complex science fiction epic set on the desert planet Arrakis.', 'uploads/images/Dune.jpg', 'uploads/files/Dune.pdf', '2025-02-18 07:23:41', 3, 231, NULL),
(59, 'Foundation', 'Isaac Asimov', '978-0553293357', 'Science Fiction', 1951, 'The first book in the series that chronicles the decline and fall of a galactic empire.', 'uploads/images/Foundation.jpg', 'uploads/files/Foundation.pdf', '2025-02-18 07:23:41', 6, 190, NULL),
(60, 'The Martian', 'Andy Weir', '978-0804139021', 'Science Fiction', 2011, 'A thrilling story of survival as an astronaut is stranded alone on Mars.', 'uploads/images/The Martian.jpg', 'uploads/files/The Martian.pdf', '2025-02-18 07:23:41', 4, 240, NULL),
(61, 'Gone Girl', 'Gillian Flynn', '978-0307588369', 'Thriller', 2012, 'A suspenseful psychological thriller about a husband who becomes the prime suspect in his wife\'s disappearance.', 'uploads/images/Gone Girl.jpg', 'uploads/files/Gone Girl.pdf', '2025-02-18 07:23:41', 7, 200, NULL),
(62, 'The Girl with the Dragon Tattoo', 'Stieg Larsson', '978-0307269759', 'Mystery', 2005, 'The first book in the Millennium series, featuring journalist Mikael Blomkvist and hacker Lisbeth Salander.', 'uploads/images/The Girl with the Dragon Tattoo.jpg', 'uploads/files/The Girl with the Dragon Tattoo.pdf', '2025-02-18 07:23:41', 9, 215, NULL),
(63, 'The Da Vinci Code', 'Dan Brown', '978-0307277671', 'Thriller', 2003, 'A fast-paced thriller involving religious conspiracy and symbolism.', 'uploads/images/The Da Vinci Code.jpg', 'uploads/files/The Da Vinci Code.pdf', '2025-02-18 07:23:41', 8, 185, NULL),
(64, 'The Silent Patient', 'Alex Michaelides', '978-1250301697', 'Thriller', 2019, 'A psychological thriller centered around a psychotherapist and his mute patient accused of murdering her husband.', 'uploads/images/The Silent Patient.jpg', 'uploads/files/The Silent Patient.pdf', '2025-02-18 07:23:41', 6, 225, NULL),
(65, 'The Alchemist', 'Paulo Coelho', '978-0062326983', 'Philosophical Fiction', 1988, 'A philosophical novel that follows the journey of a shepherd boy seeking his personal legend.', 'uploads/images/The Alchemist.jpg', 'uploads/files/The Alchemist.pdf', '2025-02-18 07:23:41', 9, 175, NULL),
(66, 'Siddhartha', 'Hermann Hesse', '978-0553208849', 'Philosophical Fiction', 1922, 'A spiritual novel about a man\'s quest for enlightenment during the time of the Buddha.', 'uploads/images/Siddhartha.jpg', 'uploads/files/Siddhartha.pdf', '2025-02-18 07:23:41', 4, 198, NULL),
(67, 'Man\'s Search for Meaning', 'Viktor Frankl', '978-0807014271', 'Psychology', 1946, 'A powerful book based on the author\'s experiences in Nazi concentration camps, exploring the importance of finding meaning in life.', 'uploads/images/Man\'s Search for Meaning .jpg', 'uploads/files/Man\'s Search for Meaning .pdf', '2025-02-18 07:23:41', 7, 205, NULL),
(68, 'Thinking, Fast and Slow', 'Daniel Kahneman', '978-0374275631', 'Psychology', 2011, 'A book on behavioral economics and cognitive biases, explaining the two systems that drive the way we think.', 'uploads/images/Thinking, Fast and Slow.jpg', 'uploads/files/Thinking, Fast and Slow.pdf', '2025-02-18 07:23:41', 5, 188, NULL),
(69, 'The Power of Habit', 'Charles Duhigg', '978-0812993712', 'Self-Help', 2012, 'Explores the science of habit formation and how habits can be changed.', 'uploads/images/The Power of Habit\' by Charles Duhigg.jpg', 'uploads/files/The Power of Habit\' by Charles Duhigg.pdf', '2025-02-18 07:23:41', 8, 192, NULL),
(70, 'Atomic Habits', 'James Clear', '978-0735211292', 'Self-Help', 2018, 'A practical guide to building good habits and breaking bad ones.', 'uploads/images/Atomic Habits An Easy & Proven Way toBuild Good Habits & Break Bad Ones James Clear.jpg', 'uploads/files/Atomic Habits An Easy & Proven Way toBuild Good Habits & Break Bad Ones James Clear.pdf', '2025-02-18 07:23:41', 6, 212, NULL),
(71, 'The 7 Habits of Highly Effective People', 'Stephen Covey', '978-0743272452', 'Self-Help', 1989, 'A classic self-help book focused on principles of personal and interpersonal effectiveness.', 'uploads/images/THE 7 HABITS OF HIGHY EFFECTIVE PEOPLE Stephen R. Covey.jpg', 'uploads/files/THE 7 HABITS OF HIGHY EFFECTIVE PEOPLE Stephen R. Covey.pdf', '2025-02-18 07:23:41', 4, 208, NULL),
(72, 'Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', '978-0062316259', 'History', 2011, 'A sweeping history of humankind, from the Stone Age to the present day.', 'uploads/images/Sapiens- A Brief History of Humankind.jpg', 'uploads/files/Sapiens- A Brief History of Humankind.pdf', '2025-02-18 07:23:41', 9, 228, NULL),
(73, 'Homo Deus: A Brief History of Tomorrow', 'Yuval Noah Harari', '978-0062464340', 'History', 2015, 'Explores the future of humankind in light of technological and scientific advancements.', 'uploads/images/Homo Deus- A Brief History of Tomorrow.jpg', 'uploads/files/Homo Deus- A Brief History of Tomorrow.pdf', '2025-02-18 07:23:41', 7, 218, NULL),
(74, '21 Lessons for the 21st Century', 'Yuval Noah Harari', '978-1784708265', 'History', 2018, 'Addresses pressing global issues and challenges facing humanity in the 21st century.', 'uploads/images/21 Lessons for the 21st Century.jpg', 'uploads/files/21 Lessons for the 21st Century.pdf', '2025-02-18 07:23:41', 5, 202, NULL),
(75, 'Educated', 'Tara Westover', '978-0399590504', 'Memoir', 2018, 'A powerful memoir about a young woman who escapes her survivalist family and pursues education.', 'uploads/images/Educated.jpg', 'uploads/files/Educated.pdf', '2025-02-18 07:23:41', 6, 235, NULL),
(76, 'Becoming', 'Michelle Obama', '978-0525634162', 'Autobiography', 2018, 'The autobiography of former First Lady Michelle Obama, sharing her life story.', 'uploads/images/Becoming.jpg', 'uploads/files/Becoming.pdf', '2025-02-18 07:23:41', 8, 245, NULL),
(77, 'The Diary of a Young Girl', 'Anne Frank', '978-0553296983', 'Autobiography', 1947, 'The poignant diary of a young Jewish girl hiding from the Nazis during World War II.', 'uploads/images/The Diary of a Young Girl.jpg', 'uploads/files/The Diary of a Young Girl.pdf', '2025-02-18 07:23:41', 4, 222, NULL),
(78, 'And Then There Were None', 'Agatha Christie', '978-0062073488', 'Mystery', 1939, 'A classic mystery novel where ten strangers are invited to an isolated island and begin to die one by one.', 'uploads/images/And Then There Were None.jpg', 'uploads/files/And Then There Were None.pdf', '2025-02-18 07:23:41', 7, 211, NULL),
(79, 'Murder on the Orient Express', 'Agatha Christie', '978-0062073471', 'Mystery', 1934, 'A famous Hercule Poirot mystery set on a snowbound train.', 'uploads/images/Murder on the Orient Express.jpg', 'uploads/files/Murder on the Orient Express.pdf', '2025-02-18 07:23:41', 5, 199, NULL),
(80, 'The Hound of the Baskervilles', 'Arthur Conan Doyle', '978-0553212525', 'Mystery', 1902, 'A thrilling Sherlock Holmes mystery involving a legendary hound.', 'uploads/images/The Hound of the Baskervilles .jpg', 'uploads/files/The Hound of the Baskervilles .pdf', '2025-02-18 07:23:41', 6, 182, NULL),
(81, 'The Girl on the Train', 'Paula Hawkins', '978-1594634024', 'Thriller', 2015, 'A psychological thriller narrated by an unreliable witness in a missing person case.', 'uploads/images/The Girl on the Train.jpg', 'uploads/files/The Girl on the Train.pdf', '2025-02-18 07:23:41', 8, 206, NULL),
(82, 'Big Little Lies', 'Liane Moriarty', '978-0399167095', 'Mystery', 2014, 'A mystery novel that explores the secrets and lies behind seemingly perfect suburban lives.', 'uploads/images/Big Little Lies.jpg', 'uploads/files/Big Little Lies.pdf', '2025-02-18 07:23:41', 4, 219, NULL),
(83, 'Little Fires Everywhere', 'Celeste Ng', '978-0735224230', 'Fiction', 2017, 'A novel that explores motherhood, class, and identity in a seemingly idyllic suburb.', 'uploads/images/Little Fires Everywhere.jpg', 'uploads/files/Little Fires Everywhere.pdf', '2025-02-18 07:23:41', 7, 231, NULL),
(84, 'Where the Crawdads Sing', 'Delia Owens', '978-0735219090', 'Fiction', 2018, 'A coming-of-age story and a murder mystery set in the marshes of North Carolina.', 'uploads/images/Where the Crawdads Sing.jpg', 'uploads/files/Where the Crawdads Sing.pdf', '2025-02-18 07:23:41', 6, 242, NULL),
(85, 'Normal People', 'Sally Rooney', '978-1631496267', 'Romance', 2018, 'A contemporary romance novel about the complex relationship between two Irish teenagers.', 'uploads/images/Normal People.jpg', 'uploads/files/Normal People.pdf', '2025-02-18 07:23:41', 5, 226, NULL),
(86, 'Me Before You', 'Jojo Moyes', '978-0670922254', 'Romance', 2012, 'A tearjerker romance novel about a young woman who becomes the caretaker of a paralyzed man.', 'uploads/images/Me Before You.jpg', 'uploads/files/Me Before You.pdf', '2025-02-18 07:23:41', 8, 238, NULL),
(87, 'The Notebook', 'Nicholas Sparks', '978-0446605237', 'Romance', 1996, 'A classic love story that spans decades.', 'uploads/images/The Notebook.jpg', 'uploads/files/The Notebook.pdf', '2025-02-18 07:23:41', 9, 248, NULL),
(88, 'Love in the Time of Cholera', 'Gabriel García Márquez', '978-0140119900', 'Literary Fiction', 1985, 'A poignant love story set against the backdrop of cholera epidemics.', 'uploads/images/Love in the Time of Cholera.jpg', 'uploads/files/Love in the Time of Cholera.pdf', '2025-02-18 07:23:41', 4, 214, NULL),
(89, 'One Hundred Years of Solitude', 'Gabriel García Márquez', '978-0060883287', 'Literary Fiction', 1967, 'A multi-generational saga of the Buendía family and the town of Macondo.', 'uploads/images/One Hundred Years of Solitude.jpg', 'uploads/files/One Hundred Years of Solitude.pdf', '2025-02-18 07:23:41', 3, 201, NULL),
(90, 'Beloved', 'Toni Morrison', '978-0307275547', 'Literary Fiction', 1987, 'A powerful novel about the legacy of slavery and its haunting effects.', 'uploads/images/Beloved .jpg', 'uploads/files/Beloved .pdf', '2025-02-18 07:23:41', 5, 196, NULL),
(91, 'Frankenstein', 'Mary Shelley', '978-0141439471', 'Gothic Horror', 1818, 'A classic gothic novel about a scientist who brings a creature to life.', 'uploads/images/Frankenstein.jpg', 'uploads/files/Frankenstein.pdf', '2025-02-18 07:23:41', 7, 189, NULL),
(92, 'Dracula', 'Bram Stoker', '978-0486414395', 'Gothic Horror', 1897, 'The iconic gothic horror novel introducing the vampire Count Dracula.', 'uploads/images/Dracula.jpg', 'uploads/files/Dracula.pdf', '2025-02-18 07:23:41', 6, 178, NULL),
(93, 'The Picture of Dorian Gray', 'Oscar Wilde', '978-0141396545', 'Gothic Horror', 1890, 'A philosophical gothic novel about a man whose portrait ages while he remains eternally young.', 'uploads/images/The Picture of Dorian Gray.jpg', 'uploads/files/The Picture of Dorian Gray.pdf', '2025-02-18 07:23:41', 4, 167, NULL),
(94, 'IT', 'Stephen King', '978-1501142972', 'Horror', 1986, 'A terrifying horror novel about a shape-shifting clown that terrorizes children.', 'uploads/images/IT.jpg', 'uploads/files/IT.pdf', '2025-02-18 07:23:41', 8, 193, NULL),
(95, 'The Shining', 'Stephen King', '978-0307743657', 'Horror', 1977, 'A chilling horror novel set in an isolated haunted hotel.', 'uploads/images/The Shining.jpg', 'uploads/files/The Shining.pdf', '2025-02-18 07:23:41', 6, 183, NULL),
(96, 'Pet Sematary', 'Stephen King', '978-1444722207', 'Horror', 1983, 'A dark horror novel exploring themes of death and the consequences of trying to overcome it.', 'uploads/images/Pet Sematary.jpg', 'uploads/files/Pet Sematary.pdf', '2025-02-18 07:23:41', 6, 173, NULL),
(97, 'The Girl with the Louding Voice', 'Abi Daré', '978-1524760978', 'Fiction', 2020, 'A powerful story about a young Nigerian girl determined to get an education.', 'uploads/images/The Girl with the Louding Voice.jpg', 'uploads/files/The Girl with the Louding Voice.pdf', '2025-02-18 07:23:41', 7, 203, NULL),
(98, 'Homegoing', 'Yaa Gyasi', '978-1101971061', 'Historical Fiction', 2016, 'A sweeping historical novel tracing the descendants of two half-sisters in Ghana and America.', 'uploads/images/Homegoing.jpg', 'uploads/files/Homegoing.pdf', '2025-02-18 07:23:41', 4, 216, NULL),
(99, 'The Nickel Boys', 'Colson Whitehead', '978-0385537071', 'Historical Fiction', 2019, 'A historical novel based on the real story of a reform school in Florida that inflicted abuse on its students.', 'uploads/images/The Nickel Boys.jpg', 'uploads/files/The Nickel Boys.pdf', '2025-02-18 07:23:41', 5, 221, NULL),
(100, 'The Underground Railroad', 'Colson Whitehead', '978-0385537033', 'Historical Fiction', 2016, 'A fictionalized account of the Underground Railroad as a literal railroad system.', 'uploads/images/The Underground Railroad.jpg', 'uploads/files/The Underground Railroad.pdf', '2025-02-18 07:23:41', 6, 233, NULL),
(101, 'Pachinko', 'Min Jin Lee', '978-1455563931', 'Historical Fiction', 2017, 'A multi-generational saga of a Korean family living in Japan across the 20th century.', 'uploads/images/Pachinko.jpg', 'uploads/files/Pachinko.pdf', '2025-02-18 07:23:41', 8, 244, NULL),
(102, 'The Nightingale', 'Kristin Hannah', '978-0312577223', 'Historical Fiction', 2015, 'A World War II historical fiction novel about two sisters in France and their resistance efforts.', 'uploads/images/The Nightingale.jpg', 'uploads/files/The Nightingale.pdf', '2025-02-18 07:23:41', 9, 255, NULL),
(103, 'All the Light We Cannot See', 'Anthony Doerr', '978-1476746586', 'Historical Fiction', 2014, 'A Pulitzer Prize-winning historical novel set during World War II, following the lives of a blind French girl and a German boy.', 'uploads/images/All the Light We Cannot See.jpg', 'uploads/files/All the Light We Cannot See.pdf', '2025-02-18 07:23:41', 7, 265, NULL),
(104, 'The Book Thief', 'Markus Zusak', '978-0375842207', 'Historical Fiction', 2005, 'A historical fiction novel narrated by Death, set in Nazi Germany and following a young girl who steals books.', 'uploads/images/The Book Thief.jpg', 'uploads/files/The Book Thief.pdf', '2025-02-18 07:23:41', 6, 275, NULL),
(105, 'Station Eleven', 'Emily St. John Mandel', '978-0385353314', 'Post-Apocalyptic', 2014, 'A post-apocalyptic novel set after a flu pandemic wipes out most of humanity.', 'uploads/images/Station Eleven.jpg', 'uploads/files/Station Eleven.pdf', '2025-02-18 07:23:41', 5, 237, NULL),
(106, 'The Road', 'Cormac McCarthy', '978-0307265430', 'Post-Apocalyptic', 2006, 'A bleak and powerful post-apocalyptic novel about a father and son journeying through a ravaged America.', 'uploads/images/The Road .jpg', 'uploads/files/The Road .pdf', '2025-02-18 07:23:41', 4, 229, NULL),
(107, 'Oryx and Crake', 'Margaret Atwood', '978-0385721677', 'Dystopian Fiction', 2003, 'The first book in the MaddAddam trilogy, exploring a dystopian future after genetic engineering goes awry.', 'uploads/images/Oryx and Crake.jpg', 'uploads/files/Oryx and Crake.pdf', '2025-02-18 07:23:41', 7, 217, NULL),
(108, 'The Handmaid\'s Tale', 'Margaret Atwood', '978-0385490813', 'Dystopian Fiction', 1985, 'A chilling dystopian novel set in Gilead, a totalitarian society where women are subjugated.', 'uploads/images/The Handmaid\'s Tale.jpg', 'uploads/files/The Handmaid\'s Tale.pdf', '2025-02-18 07:23:41', 6, 209, NULL),
(109, 'Brave New World', 'Aldous Huxley', '978-0060850524', 'Dystopian Fiction', 1932, 'A classic dystopian novel exploring a future society based on technological control and social engineering.', 'uploads/images/Brave New World.jpg', 'uploads/files/Brave New World.pdf', '2025-02-18 07:23:41', 5, 197, NULL),
(110, 'Fahrenheit 451', 'Ray Bradbury', '978-1451673319', 'Dystopian Fiction', 1953, 'A dystopian novel set in a future where books are burned and independent thought is suppressed.', 'uploads/images/Fahrenheit 451 .jpg', 'uploads/files/Fahrenheit 451 .pdf', '2025-02-18 07:23:41', 8, 187, NULL),
(111, 'Cloud Cuckoo Land', 'Anthony Doerr', '978-1501153435', 'Historical Fiction', 2021, 'An ambitious novel that weaves together multiple timelines from ancient Constantinople to present-day Idaho and a spaceship in the future.', 'uploads/images/Cloud Cuckoo Land .jpg', 'uploads/files/Cloud Cuckoo Land.pdf', '2025-02-18 07:23:41', 6, 257, NULL),
(112, 'Project Hail Mary', 'Andy Weir', '978-0593135204', 'Science Fiction', 2021, 'A science fiction adventure novel about an amnesiac astronaut sent on a mission to save Earth.', 'uploads/images/Project Hail Mary.jpg', 'uploads/files/Project Hail Mary.pdf', '2025-02-18 07:23:41', 7, 267, NULL),
(113, 'Klara and the Sun', 'Kazuo Ishiguro', '978-0593318178', 'Science Fiction', 2021, 'A science fiction novel narrated by an Artificial Friend observing the world and the humans around her.', 'uploads/images/Klara and the Sun.jpg', 'uploads/files/Klara and the Sun.pdf', '2025-02-18 07:23:41', 8, 277, NULL),
(114, 'Leave the World Behind', 'Rumaan Alam', '978-0062669082', 'Thriller', 2020, 'A suspenseful and unsettling thriller about two families forced to shelter together during a mysterious blackout.', 'uploads/images/Leave the World Behind.jpg', 'uploads/files/Leave the World Behind.pdf', '2025-02-18 07:23:41', 9, 287, NULL),
(115, 'Hamnet', 'Maggie O\'Farrell', '978-0544977183', 'Historical Fiction', 2020, 'A historical fiction novel reimagining the life and death of Shakespeare\'s son, Hamnet.', 'uploads/images/Hamnet .jpg', 'uploads/files/Hamnet .pdf', '2025-02-18 07:23:41', 7, 297, NULL),
(116, 'The Midnight Library', 'Matt Haig', '978-0525559471', 'Fiction', 2020, 'A thought-provoking novel about a woman who finds a library between life and death, containing infinite possibilities.', 'uploads/images/The Midnight Library .jpg', 'uploads/files/The Midnight Library .pdf', '2025-02-18 07:23:41', 6, 308, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `book_requests`
--

CREATE TABLE `book_requests` (
  `request_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_requests`
--

INSERT INTO `book_requests` (`request_id`, `student_id`, `book_id`, `request_date`, `status`) VALUES
(1, 1, 20, '2024-12-18 15:17:17', 'approved'),
(2, 1, 12, '2024-12-18 15:20:03', 'approved'),
(3, 1, 13, '2024-12-18 15:24:10', 'approved'),
(4, 1, 20, '2025-01-03 13:46:42', 'approved'),
(5, 1, 19, '2025-02-07 00:33:28', 'approved'),
(6, 1, 21, '2025-02-15 18:45:29', 'approved'),
(7, 1, 14, '2025-02-15 20:38:58', 'pending'),
(8, 1, 44, '2025-02-15 21:42:54', 'approved'),
(9, 1, 12, '2025-02-15 22:04:25', 'approved'),
(10, 3, 14, '2025-02-20 00:30:39', 'approved'),
(11, 1, 42, '2025-02-25 04:10:32', 'pending'),
(12, 3, 58, '2025-03-18 10:21:19', 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `borrowing_history`
--

CREATE TABLE `borrowing_history` (
  `history_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `issue_date` datetime NOT NULL,
  `return_date` datetime DEFAULT NULL,
  `status` enum('issued','returned') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowing_history`
--

INSERT INTO `borrowing_history` (`history_id`, `student_id`, `book_id`, `issue_date`, `return_date`, `status`) VALUES
(4, 1, 12, '2024-12-11 13:44:49', '2024-12-11 17:30:51', 'returned'),
(5, 1, 13, '2024-12-11 13:45:27', '2024-12-11 16:53:08', 'returned'),
(6, 1, 13, '2024-12-11 13:46:00', '2024-12-11 16:53:08', 'returned'),
(7, 1, 38, '2024-12-11 16:54:50', '2024-12-27 20:55:01', 'returned'),
(8, 1, 20, '2024-12-11 16:57:38', '2024-12-16 14:55:12', 'returned'),
(9, 1, 22, '2024-12-14 19:57:00', '2024-12-27 20:55:08', 'returned'),
(10, 1, 20, '2024-12-16 13:51:24', '2024-12-16 14:55:12', 'returned'),
(11, 1, 20, '2024-12-16 13:53:01', '2024-12-16 14:55:12', 'returned'),
(12, 1, 33, '2024-12-16 14:57:16', '2024-12-27 20:55:15', 'returned'),
(14, 1, 44, '2024-12-18 19:21:11', '2024-12-18 19:26:24', 'returned'),
(18, 1, 21, '2024-12-18 22:44:30', '2024-12-27 20:55:26', 'returned'),
(19, 1, 12, '2024-12-18 22:58:32', '2024-12-18 22:59:04', 'returned'),
(20, 1, 12, '2024-12-18 22:59:20', '2024-12-18 22:59:45', 'returned'),
(21, 1, 12, '2024-12-18 23:25:14', '2025-02-18 22:44:16', 'returned'),
(22, 1, 13, '2024-12-18 23:37:32', '2025-04-09 13:42:18', 'returned'),
(23, 1, 20, '2024-12-18 23:46:26', '2024-12-27 20:01:54', 'returned'),
(24, 1, 24, '2024-12-24 15:46:22', '2025-04-11 18:16:52', 'returned'),
(25, 1, 26, '2024-12-27 19:55:58', '2025-02-20 05:43:54', 'returned'),
(26, 1, 19, '2024-12-27 20:56:08', '2025-03-22 13:04:36', 'returned'),
(27, 1, 47, '2025-01-16 15:40:44', '2025-01-16 15:44:21', 'returned'),
(28, 1, 47, '2025-01-16 15:41:37', '2025-01-16 15:44:21', 'returned'),
(29, 1, 47, '2025-01-16 15:46:06', '2025-02-20 05:44:49', 'returned'),
(30, 1, 46, '2025-01-16 15:53:06', '2025-01-16 16:04:11', 'returned'),
(31, 1, 26, '2025-01-27 15:31:24', '2025-02-20 05:43:54', 'returned'),
(32, 1, 36, '2025-02-07 10:06:12', '2025-04-11 18:17:06', 'returned'),
(33, 1, 44, '2025-02-07 10:19:41', '2025-02-18 22:43:53', 'returned'),
(34, 1, 14, '2025-02-10 09:39:37', '2025-02-10 09:41:30', 'returned'),
(35, 1, 44, '2025-02-16 05:43:34', '2025-02-18 22:43:53', 'returned'),
(36, 1, 12, '2025-02-16 06:04:10', '2025-02-18 22:44:16', 'returned'),
(37, 1, 25, '2025-02-17 17:01:31', NULL, 'issued'),
(38, 1, 102, '2025-02-18 19:34:58', '2025-04-11 18:20:10', 'returned'),
(39, 1, 95, '2025-02-18 19:35:48', '2025-04-11 18:17:16', 'returned'),
(40, 3, 58, '2025-02-20 08:27:52', '2025-02-20 08:29:35', 'returned'),
(41, 3, 14, '2025-02-20 08:31:11', '2025-03-18 18:20:40', 'returned'),
(42, 1, 62, '2025-03-06 12:10:35', '2025-03-22 11:58:00', 'returned'),
(43, 1, 62, '2025-03-06 12:12:37', '2025-03-22 11:58:00', 'returned'),
(44, 1, 19, '2025-04-11 18:18:55', NULL, 'issued'),
(45, 1, 12, '2025-04-11 18:18:59', '2025-06-02 13:48:36', 'returned'),
(46, 1, 21, '2025-04-11 18:19:52', '2025-04-11 18:20:26', 'returned'),
(47, 1, 20, '2025-04-11 18:19:55', NULL, 'issued'),
(48, 4, 116, '2025-06-02 13:45:19', NULL, 'issued'),
(49, 1, 48, '2025-07-10 19:17:24', NULL, 'issued');

-- --------------------------------------------------------

--
-- Table structure for table `fines`
--

CREATE TABLE `fines` (
  `fine_id` int(11) NOT NULL,
  `issue_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `fine_type` enum('damage','lost','other') DEFAULT NULL,
  `fine_amount` decimal(10,2) NOT NULL,
  `days_overdue` int(11) NOT NULL,
  `status` enum('unpaid','paid') DEFAULT 'unpaid',
  `description` text DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fines`
--

INSERT INTO `fines` (`fine_id`, `issue_id`, `student_id`, `fine_type`, `fine_amount`, `days_overdue`, `status`, `description`, `payment_id`, `created_at`, `updated_at`) VALUES
(13, 53, 1, 'damage', '32.00', 0, 'unpaid', 'Damage book', NULL, '2025-03-21 11:27:55', '2025-03-21 11:27:55'),
(14, NULL, 3, 'other', '0.10', 0, 'paid', 'user fee', 27, '2025-03-21 11:29:53', '2025-03-22 04:46:14'),
(15, 31, 1, 'lost', '15.00', 0, 'paid', 'Lost book', 9, '2025-03-21 11:53:26', '2025-03-21 11:57:40'),
(16, 52, 1, 'damage', '23.00', 0, 'unpaid', 'damage', NULL, '2025-03-21 12:03:01', '2025-03-21 12:03:01'),
(17, 48, 1, 'lost', '1.00', 0, 'paid', 'lost', 10, '2025-03-21 12:04:10', '2025-03-21 12:18:17'),
(18, 47, 1, 'lost', '2.00', 0, 'unpaid', 'lost', NULL, '2025-03-21 12:04:23', '2025-03-21 12:04:23'),
(19, 43, 1, 'lost', '1.00', 0, 'paid', 'lost', 24, '2025-03-21 12:04:36', '2025-03-22 03:23:37'),
(20, 35, 1, 'lost', '2.00', 0, 'unpaid', 'lost', NULL, '2025-03-21 12:04:46', '2025-03-21 12:04:46'),
(21, 34, 1, 'lost', '0.10', 0, 'unpaid', 'lost', NULL, '2025-03-21 12:05:04', '2025-03-21 12:05:04'),
(22, 32, 1, 'lost', '3.00', 0, 'unpaid', 'lost', NULL, '2025-03-21 12:05:28', '2025-03-21 12:05:28'),
(23, 49, 1, 'lost', '4.00', 0, 'unpaid', 'losa', NULL, '2025-03-21 12:05:47', '2025-03-21 12:05:47'),
(24, 36, 1, 'lost', '3.00', 0, 'unpaid', 'l', NULL, '2025-03-21 12:07:06', '2025-03-21 12:07:06'),
(25, 42, 1, 'lost', '0.01', 0, 'paid', 'la', 11, '2025-03-21 12:07:19', '2025-03-21 12:31:41'),
(26, NULL, 3, 'other', '0.01', 0, 'paid', 'Book card', 34, '2025-03-22 04:01:47', '2025-04-10 12:32:38');

-- --------------------------------------------------------

--
-- Table structure for table `issue_book`
--

CREATE TABLE `issue_book` (
  `issue_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `issue_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `return_date` timestamp NULL DEFAULT NULL,
  `status` enum('issued','returned') DEFAULT 'issued'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `issue_book`
--

INSERT INTO `issue_book` (`issue_id`, `book_id`, `student_id`, `issue_date`, `return_date`, `status`) VALUES
(13, 12, 1, '2024-12-11 05:44:49', '2024-12-11 09:30:51', 'returned'),
(14, 13, 1, '2024-12-11 05:45:27', '2024-12-11 08:53:08', 'returned'),
(16, 38, 1, '2024-12-11 08:54:50', '2024-12-27 12:55:01', 'returned'),
(17, 20, 1, '2024-12-11 08:57:38', '2024-12-17 08:41:37', 'returned'),
(18, 22, 1, '2024-12-14 11:57:00', '2024-12-27 12:55:08', 'returned'),
(20, 20, 1, '2024-12-16 05:51:24', '2024-12-16 06:55:12', 'returned'),
(21, 20, 1, '2024-12-16 05:53:01', '2024-12-27 12:01:54', 'returned'),
(22, 33, 1, '2024-12-16 06:57:16', '2024-12-27 12:55:15', 'returned'),
(24, 44, 1, '2024-12-18 11:21:11', '2024-12-18 11:26:24', 'returned'),
(28, 21, 1, '2024-12-18 14:44:30', '2024-12-27 12:55:26', 'returned'),
(29, 12, 1, '2024-12-18 14:58:32', '2024-12-18 14:59:04', 'returned'),
(30, 12, 1, '2024-12-18 14:59:20', '2024-12-18 14:59:45', 'returned'),
(31, 12, 1, '2024-12-18 15:25:14', '2025-03-22 03:21:22', 'returned'),
(32, 13, 1, '2024-12-18 15:37:32', '2025-04-09 05:42:18', 'returned'),
(33, 20, 1, '2024-12-18 15:46:26', '2024-12-27 12:02:10', 'returned'),
(34, 24, 1, '2024-12-24 07:46:22', '2025-04-11 10:16:52', 'returned'),
(35, 26, 1, '2024-12-27 11:55:58', '2025-04-10 15:22:08', 'returned'),
(36, 19, 1, '2024-12-27 12:56:08', '2025-03-22 05:04:36', 'returned'),
(37, 47, 1, '2025-01-16 07:40:44', '2025-01-16 07:44:21', 'returned'),
(38, 47, 1, '2025-01-16 07:41:37', '2025-01-16 07:44:28', 'returned'),
(39, 47, 1, '2025-01-16 07:46:06', '2025-02-19 21:44:49', 'returned'),
(40, 46, 1, '2025-01-16 07:53:06', '2025-01-16 08:04:11', 'returned'),
(41, 26, 1, '2025-01-27 07:31:24', '2025-02-19 21:43:54', 'returned'),
(42, 36, 1, '2025-02-07 02:06:12', '2025-04-11 10:17:06', 'returned'),
(43, 44, 1, '2025-02-07 02:19:41', '2025-03-22 03:15:57', 'returned'),
(44, 14, 1, '2025-02-10 01:39:37', '2025-02-10 01:41:30', 'returned'),
(45, 44, 1, '2025-02-15 21:43:34', '2025-02-18 14:43:53', 'returned'),
(46, 12, 1, '2025-02-15 22:04:10', '2025-02-18 14:44:16', 'returned'),
(47, 25, 1, '2025-02-17 09:01:31', '2025-02-24 02:01:31', 'issued'),
(48, 102, 1, '2025-02-18 11:34:58', '2025-04-11 10:20:10', 'returned'),
(49, 95, 1, '2025-02-18 11:35:48', '2025-04-11 10:17:16', 'returned'),
(50, 58, 3, '2025-02-20 00:27:52', '2025-02-20 00:29:35', 'returned'),
(51, 14, 3, '2025-02-20 00:31:11', '2025-03-18 10:20:40', 'returned'),
(52, 62, 1, '2025-03-06 04:10:35', '2025-03-22 04:50:36', 'returned'),
(53, 62, 1, '2025-03-06 04:12:37', '2025-03-22 05:02:17', 'returned'),
(54, 19, 1, '2025-04-11 10:18:55', '2025-04-18 04:18:55', 'issued'),
(55, 12, 1, '2025-04-11 10:18:59', '2025-06-02 05:48:36', 'returned'),
(56, 21, 1, '2025-04-11 10:19:52', '2025-04-11 10:20:26', 'returned'),
(57, 20, 1, '2025-04-11 10:19:55', '2025-04-18 04:19:55', 'issued'),
(58, 116, 4, '2025-06-02 05:45:19', '2025-06-08 23:45:19', 'issued'),
(59, 48, 1, '2025-07-10 17:17:24', '2025-07-17 17:17:24', 'issued');

--
-- Triggers `issue_book`
--
DELIMITER $$
CREATE TRIGGER `after_issue_book_insert` AFTER INSERT ON `issue_book` FOR EACH ROW BEGIN
    INSERT INTO borrowing_history (student_id, book_id, issue_date, status)
    VALUES (NEW.student_id, NEW.book_id, NEW.issue_date, 'issued');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_issue_book_update` AFTER UPDATE ON `issue_book` FOR EACH ROW BEGIN
    IF NEW.status = 'returned' THEN
        UPDATE borrowing_history
        SET return_date = NEW.return_date, status = 'returned'
        WHERE student_id = NEW.student_id AND book_id = NEW.book_id AND status = 'issued';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sent_by` int(11) NOT NULL,
  `received_by` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `seen` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sent_by`, `received_by`, `message_text`, `sent_at`, `seen`) VALUES
(1, 1, 2, 'ogg', '2025-04-14 07:56:25', 0),
(2, 1, 3, 'here', '2025-04-14 07:56:50', 1),
(3, 1, 1, 'ok', '2025-04-14 09:32:14', 1),
(4, 1, 1, 'book', '2025-04-14 09:34:53', 1),
(5, 4, 1, 'no new books', '2025-06-02 05:47:06', 0);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `issue_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `payment_method` enum('cash','card','online') NOT NULL,
  `transaction_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `student_id`, `issue_id`, `amount`, `payment_date`, `payment_method`, `transaction_id`) VALUES
(1, 1, NULL, '4.95', '2025-02-19 19:56:48', 'online', '8AD08684BM1087352'),
(2, 1, NULL, '61.38', '2025-02-19 19:59:46', 'online', '7TD087696U946671F'),
(3, 1, NULL, '4.95', '2025-02-19 20:00:54', 'online', '45S985248B392264H'),
(4, 1, NULL, '52.47', '2025-02-19 21:38:26', 'online', '5GV09365XM344411K'),
(5, 1, 36, '53.00', '2025-02-19 23:32:06', 'online', '93G45322SN896964C'),
(6, 1, 32, '56.43', '2025-02-20 00:36:45', 'online', '26V022052B609351H'),
(7, 1, 34, '50.49', '2025-02-24 23:52:05', 'online', '2BD81229987609642'),
(8, 1, 42, '11.89', '2025-02-26 03:17:15', 'online', '2X741341L5983603C'),
(9, 1, 31, '15.00', '2025-03-21 11:57:40', 'online', '70P23087EA244672Y'),
(10, 1, 48, '1.00', '2025-03-21 12:18:17', 'cash', 'LMS202403015070191'),
(11, 1, 42, '0.01', '2025-03-21 12:31:41', 'card', 'LMS202403015070192'),
(12, 1, 47, '0.99', '2025-03-21 12:40:34', 'cash', 'LMS202403015070193'),
(13, 1, 49, '0.00', '2025-03-21 12:42:55', 'card', 'LMS202403015070194'),
(14, 1, 49, '0.00', '2025-03-21 12:43:05', 'card', 'LMS202403015070194'),
(15, 1, 49, '0.00', '2025-03-21 12:43:35', 'card', 'LMS202403015070194'),
(16, 1, 47, '0.99', '2025-03-21 12:53:25', 'online', '6GV92293VR2042908'),
(17, 1, 49, '23.76', '2025-03-21 12:55:43', 'cash', 'LMS202403015070195'),
(18, 1, 49, '23.76', '2025-03-21 12:56:56', 'online', '33E40982B9014853P'),
(19, 1, 48, '25.74', '2025-03-21 12:58:14', 'cash', 'LMS202403015070196'),
(20, 1, 48, '25.74', '2025-03-21 12:59:07', 'online', '37S20089V47154700'),
(21, 1, 43, '35.64', '2025-03-22 03:15:57', 'online', '14G760108M138433X'),
(22, 1, 31, '92.07', '2025-03-22 03:20:08', 'cash', 'LMS202403015070197'),
(23, 1, 31, '92.07', '2025-03-22 03:21:22', 'online', '368581094A880720A'),
(24, 1, 43, '1.00', '2025-03-22 03:23:37', 'online', '2H1186592S5940503'),
(25, 1, 52, '0.10', '2025-03-22 03:58:00', 'cash', 'LMS202403015070196'),
(26, 1, 52, '0.10', '2025-03-22 03:58:10', 'cash', 'LMS202403015070196'),
(27, 3, NULL, '0.10', '2025-03-22 04:46:14', 'online', '19J69778B7279251M'),
(28, 1, 52, '0.10', '2025-03-22 04:50:36', 'online', '9XV289146H8225108'),
(29, 1, 53, '14.85', '2025-03-22 04:54:57', 'cash', 'LMS202403015070197'),
(30, 1, 53, '14.85', '2025-03-22 04:55:11', 'cash', 'LMS202403015070197'),
(31, 1, 53, '14.85', '2025-03-22 05:02:17', 'cash', 'LMS202403015070198'),
(32, 1, 36, '82.17', '2025-03-22 05:04:36', 'online', '1XJ93863SW7027505'),
(33, 1, 32, '103.95', '2025-04-09 05:42:18', 'online', '7GC54672LJ718162J'),
(34, 3, NULL, '0.01', '2025-04-10 12:32:38', 'online', '9ER9108042569863T'),
(35, 1, 35, '100.98', '2025-04-10 15:22:08', 'online', '4BC99712GM833202J');

-- --------------------------------------------------------

--
-- Table structure for table `penalties`
--

CREATE TABLE `penalties` (
  `penalty_id` int(11) NOT NULL,
  `issue_id` int(11) NOT NULL,
  `penalty_amount` decimal(10,2) NOT NULL,
  `days_overdue` int(11) NOT NULL,
  `status` enum('unpaid','paid') DEFAULT 'unpaid',
  `payment_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `student_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penalties`
--

INSERT INTO `penalties` (`penalty_id`, `issue_id`, `penalty_amount`, `days_overdue`, `status`, `payment_id`, `created_at`, `updated_at`, `student_id`) VALUES
(11, 43, '35.64', 36, 'paid', 21, '2025-03-22 03:15:20', '2025-03-22 03:15:57', 1),
(12, 31, '92.07', 93, 'paid', 23, '2025-03-22 03:19:21', '2025-03-22 03:21:22', 1),
(13, 52, '0.10', 15, 'paid', 28, '2025-03-22 03:22:27', '2025-03-22 04:50:36', 1),
(14, 53, '14.85', 15, 'paid', 31, '2025-03-22 04:52:42', '2025-03-22 05:02:17', 1),
(15, 36, '82.17', 83, 'paid', 32, '2025-03-22 05:03:06', '2025-03-22 05:04:36', 1),
(16, 32, '103.95', 105, 'paid', 33, '2025-04-09 05:41:40', '2025-04-09 05:42:18', 1),
(17, 35, '100.98', 102, 'paid', 35, '2025-04-10 15:21:03', '2025-04-10 15:22:08', 1),
(18, 47, '97.02', 47, 'unpaid', NULL, '2025-04-12 08:02:43', '2025-06-02 05:48:07', 1),
(19, 54, '4545.00', 181, 'unpaid', NULL, '2025-10-16 16:57:15', '2025-10-16 16:57:15', 1);

-- --------------------------------------------------------

--
-- Table structure for table `reserved`
--

CREATE TABLE `reserved` (
  `student_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reserved_id` int(11) NOT NULL,
  `reserved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reserved`
--

INSERT INTO `reserved` (`student_id`, `book_id`, `added_at`, `reserved_id`, `reserved_at`) VALUES
(1, 48, '2025-02-07 01:35:36', 9, '2025-02-07 01:35:36'),
(1, 47, '2025-02-07 01:36:28', 10, '2025-02-07 01:36:28'),
(1, 42, '2025-02-07 01:38:36', 11, '2025-02-07 01:38:36'),
(1, 41, '2025-02-07 01:38:48', 12, '2025-02-07 01:38:48'),
(1, 12, '2025-02-07 01:44:29', 13, '2025-02-07 01:44:29'),
(1, 13, '2025-02-07 01:44:31', 14, '2025-02-07 01:44:31'),
(1, 14, '2025-02-10 01:53:54', 21, '2025-02-10 01:53:54'),
(3, 14, '2025-02-20 00:30:15', 22, '2025-02-20 00:30:15'),
(3, 12, '2025-03-19 05:59:40', 23, '2025-03-19 05:59:40'),
(4, 12, '2025-06-02 05:45:53', 24, '2025-06-02 05:45:53');

-- --------------------------------------------------------

--
-- Table structure for table `students_registration`
--

CREATE TABLE `students_registration` (
  `student_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `enrollment_no` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact` varchar(15) DEFAULT NULL,
  `student_img` text DEFAULT NULL,
  `approved` enum('active','inactive') DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students_registration`
--

INSERT INTO `students_registration` (`student_id`, `first_name`, `last_name`, `enrollment_no`, `username`, `password`, `email`, `contact`, `student_img`, `approved`, `created_at`, `updated_at`) VALUES
(1, 'Marvin', 'Sikanyika', 'MS2024203800105', 'marvin', '$2y$10$nOr2gDUzk58.9lbgwVfHmOI9M/sKzzPV4Nf8TvQ4/2nSqSf4PLcoC', 'marvin@qq.com', '0969777651', 'uploads/images/Machel marvin 3.jpg', 'active', '2024-12-10 05:36:05', '2025-04-12 10:02:44'),
(2, 'Josh', 'chileshe', 'MS2024203800103', 'josh', '$2y$10$aYIfd45nzQ3PwtQtfxiiYexfe1neVZFu51i8muktf6Y2QNw.T3/Wi', 'josh@qq.com', '13291896983', 'uploads/ma.png', 'inactive', '2024-12-12 09:29:29', '2025-04-12 10:39:38'),
(3, 'Tom', 'bobs', 'MS2024203800109', 'tom', '$2y$10$VeUT7A0Vak9iLh2Ap3ZR7.oxK7e4uVsQP5kPF9uB9GXUwIGFFCD/.', 'tom@qq.com', '12391887863', 'uploads/images/Young Man in Autumn.jpeg', 'active', '2025-02-20 00:26:21', '2025-04-14 04:44:58'),
(4, 'Yarie', 'Camar', 'MS2024203800110', 'yarie', '$2y$10$7g0y0XwzWOBnmEK.ooo6ZO/7NEp8CqUcMovH7yOKCiH5EgYycLAUa', 'yarie@123.com', '1111', 'uploads/images/helicopters-free-screensavers.jpg', 'active', '2025-06-02 05:43:59', '2025-06-02 05:44:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_login`
--
ALTER TABLE `admin_login`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `book_requests`
--
ALTER TABLE `book_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `borrowing_history`
--
ALTER TABLE `borrowing_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `fines`
--
ALTER TABLE `fines`
  ADD PRIMARY KEY (`fine_id`),
  ADD KEY `issue_id` (`issue_id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `FK_fines_students` (`student_id`);

--
-- Indexes for table `issue_book`
--
ALTER TABLE `issue_book`
  ADD PRIMARY KEY (`issue_id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_sent_by` (`sent_by`),
  ADD KEY `idx_received_by` (`received_by`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `FK_payments_issue_book` (`issue_id`);

--
-- Indexes for table `penalties`
--
ALTER TABLE `penalties`
  ADD PRIMARY KEY (`penalty_id`),
  ADD KEY `issue_id` (`issue_id`),
  ADD KEY `fk_student` (`student_id`),
  ADD KEY `fk_penalty_payment` (`payment_id`);

--
-- Indexes for table `reserved`
--
ALTER TABLE `reserved`
  ADD PRIMARY KEY (`reserved_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_book_id` (`book_id`);

--
-- Indexes for table `students_registration`
--
ALTER TABLE `students_registration`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `enrollment_no` (`enrollment_no`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_login`
--
ALTER TABLE `admin_login`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `book_requests`
--
ALTER TABLE `book_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `borrowing_history`
--
ALTER TABLE `borrowing_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `fines`
--
ALTER TABLE `fines`
  MODIFY `fine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `issue_book`
--
ALTER TABLE `issue_book`
  MODIFY `issue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `penalties`
--
ALTER TABLE `penalties`
  MODIFY `penalty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `reserved`
--
ALTER TABLE `reserved`
  MODIFY `reserved_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `students_registration`
--
ALTER TABLE `students_registration`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `book_requests`
--
ALTER TABLE `book_requests`
  ADD CONSTRAINT `book_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students_registration` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `book_requests_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `borrowing_history`
--
ALTER TABLE `borrowing_history`
  ADD CONSTRAINT `borrowing_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students_registration` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borrowing_history_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fines`
--
ALTER TABLE `fines`
  ADD CONSTRAINT `FK_fines_students` FOREIGN KEY (`student_id`) REFERENCES `students_registration` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fines_ibfk_1` FOREIGN KEY (`issue_id`) REFERENCES `issue_book` (`issue_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fines_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE SET NULL;

--
-- Constraints for table `issue_book`
--
ALTER TABLE `issue_book`
  ADD CONSTRAINT `issue_book_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `issue_book_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students_registration` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `FK_received_by` FOREIGN KEY (`received_by`) REFERENCES `students_registration` (`student_id`),
  ADD CONSTRAINT `FK_sent_by` FOREIGN KEY (`sent_by`) REFERENCES `students_registration` (`student_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `FK_payments_issue_book` FOREIGN KEY (`issue_id`) REFERENCES `issue_book` (`issue_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students_registration` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `penalties`
--
ALTER TABLE `penalties`
  ADD CONSTRAINT `fk_penalty_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_student` FOREIGN KEY (`student_id`) REFERENCES `students_registration` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `penalties_ibfk_1` FOREIGN KEY (`issue_id`) REFERENCES `issue_book` (`issue_id`) ON DELETE CASCADE;

--
-- Constraints for table `reserved`
--
ALTER TABLE `reserved`
  ADD CONSTRAINT `reserved_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students_registration` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reserved_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reserved_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students_registration` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reserved_ibfk_4` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
