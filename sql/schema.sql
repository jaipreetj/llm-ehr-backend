DROP DATABASE IF EXISTS llm_ehr_db;
CREATE DATABASE llm_ehr_db;
USE llm_ehr_db;

-- =====================================================
-- Users
-- =====================================================
CREATE TABLE Users (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    LastName VARCHAR(100) NOT NULL,
    Username VARCHAR(100) NOT NULL,
    PasswordHash VARCHAR(255) NOT NULL,
    Role ENUM('clinician','researcher','admin') DEFAULT 'clinician',
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Patients
-- =====================================================
CREATE TABLE Patients (
    PatientID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(100),
    LastName VARCHAR(100),
    DateOfBirth DATE,
    Sex ENUM('Male','Female','Other'),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- EHR_Inputs
-- =====================================================
CREATE TABLE EHR_Inputs (
    EHRID INT AUTO_INCREMENT PRIMARY KEY,
    PatientID INT NOT NULL,
    InputJSON JSON NULL,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PDFPath VARCHAR(500),

    FOREIGN KEY (PatientID) REFERENCES Patients(PatientID)
);

-- =====================================================
-- LLM_Reports
-- =====================================================
CREATE TABLE LLM_Reports (
    ReportID INT AUTO_INCREMENT PRIMARY KEY,
    EHRID INT NOT NULL,
    GeneratedBy INT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PDFPath VARCHAR(500),
    Prompt VARCHAR(100),

    FOREIGN KEY (EHRID) REFERENCES EHR_Inputs(EHRID)
);

-- =====================================================
-- Literature_DB
-- =====================================================
CREATE TABLE Literature_DB (
    DocID INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(255),
    ReportText LONGTEXT,
    Source VARCHAR(255),
    PDFPath VARCHAR(500),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- Feedback
-- =====================================================
CREATE TABLE Feedback (
    FeedbackID INT AUTO_INCREMENT PRIMARY KEY,
    ReportID INT NOT NULL,
    UserID INT NOT NULL,
    Rating INT CHECK (Rating BETWEEN 1 AND 5),
    Comments TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (ReportID) REFERENCES LLM_Reports(ReportID),
    FOREIGN KEY (UserID) REFERENCES Users(UserID)
);

-- =====================================================
-- Prompt_History
-- =====================================================
CREATE TABLE Prompt_History (
    PromptID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT,
    PromptText LONGTEXT,
    LLMReportPath VARCHAR(500),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (UserID) REFERENCES Users(UserID)
);
