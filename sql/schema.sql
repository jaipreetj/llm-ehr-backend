DROP DATABASE IF EXISTS llm_ehr_db;
CREATE DATABASE llm_ehr_db;
USE llm_ehr_db;

-- =====================================================
-- Users
-- Clinicians/researchers with login credentials
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
-- ID, name, photo, demographics, condition summary
-- =====================================================
CREATE TABLE Patients (
    PatientID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(100),
    LastName VARCHAR(100),
    DateOfBirth DATE,
    Sex ENUM('Male','Female','Other'),
    -- PhotoPath VARCHAR(255),
    -- ConditionSummary TEXT,
    -- CreatedBy INT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    -- FOREIGN KEY (CreatedBy) REFERENCES Users(UserID)
);

-- =====================================================
-- EHR_Inputs
-- Structured clinical data per patient (lab, symptoms, vitals)
-- =====================================================
CREATE TABLE EHR_Inputs (
    EHRID INT AUTO_INCREMENT PRIMARY KEY,
    PatientID INT NOT NULL,
    InputJSON JSON NULL,   -- flexible data input
    -- UploadedBy INT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PDFPath VARCHAR(500),

    FOREIGN KEY (PatientID) REFERENCES Patients(PatientID)
    -- FOREIGN KEY (UploadedBy) REFERENCES Users(UserID)
);

-- =====================================================
-- LLM_Reports
-- Diagnostic/treatment reports per query
-- =====================================================
CREATE TABLE LLM_Reports (
    ReportID INT AUTO_INCREMENT PRIMARY KEY,
    EHRID INT NOT NULL,
    -- ReportText LONGTEXT NOT NULL,
    GeneratedBy INT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PDFPath VARCHAR(500),
    Prompt VARCHAR(100),

    FOREIGN KEY (EHRID) REFERENCES EHR_Inputs(EHRID)
    -- FOREIGN KEY (GeneratedBy) REFERENCES Users(UserID)
);

-- =====================================================
-- Literature_DB
-- Embedded abstracts/articles for RAG
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
-- User feedback on LLM report quality
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
-- Store prompt structure and LLM responses
-- =====================================================
CREATE TABLE Prompt_History (
    PromptID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT,
    PromptText LONGTEXT,
    LLMReportPath VARCHAR(500),
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (UserID) REFERENCES Users(UserID)
);
