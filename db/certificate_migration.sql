ALTER TABLE learning_modules
    ADD COLUMN certificate_template VARCHAR(255) DEFAULT NULL AFTER module_data;

ALTER TABLE certificates
    ADD COLUMN module_id INT NULL AFTER user_id,
    ADD COLUMN recipient_name VARCHAR(201) NULL AFTER module_id,
    ADD UNIQUE KEY uq_certificates_user_module (user_id, module_id),
    ADD KEY fk_certificates_module (module_id);

ALTER TABLE certificates
    ADD CONSTRAINT fk_certificates_module
    FOREIGN KEY (module_id) REFERENCES learning_modules (module_id) ON DELETE CASCADE;