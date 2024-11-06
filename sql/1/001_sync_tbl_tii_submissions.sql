CREATE TABLE IF NOT EXISTS sync.tbl_tii_submissions (
        paabgabe_id INT NOT NULL,
        projektarbeit_id INT NOT NULL,
        submission_id UUID NOT NULL,
        status VARCHAR(16) NOT NULL
);

COMMENT ON TABLE sync.tbl_tii_submissions IS 'Synchronization table between Abgabetool and Turnitin';
COMMENT ON COLUMN sync.tbl_tii_submissions.paabgabe_id IS 'Reference to the table campus.tbl_paabgabe';
COMMENT ON COLUMN sync.tbl_tii_submissions.projektarbeit_id IS 'Reference to the table lehre.tbl_projektarbeit';
COMMENT ON COLUMN sync.tbl_tii_submissions.submission_id IS 'Submission id provided by Turnitin';
COMMENT ON COLUMN sync.tbl_tii_submissions.status IS 'Status of the submission: CREATED > COMPLETE > SENT > DONE';

DO $$
BEGIN
        ALTER TABLE sync.tbl_tii_submissions ADD CONSTRAINT tbl_tii_submissions_pkey PRIMARY KEY (paabgabe_id, projektarbeit_id, submission_id);
        EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
        ALTER TABLE sync.tbl_tii_submissions ADD CONSTRAINT tbl_tii_submissions_paabgabe_id_fkey FOREIGN KEY (paabgabe_id) REFERENCES campus.tbl_paabgabe (paabgabe_id) ON DELETE RESTRICT ON UPDATE CASCADE;
        EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
        ALTER TABLE sync.tbl_tii_submissions ADD CONSTRAINT tbl_tii_submissions_projektarbeit_id_fkey FOREIGN KEY (projektarbeit_id) REFERENCES lehre.tbl_projektarbeit (projektarbeit_id) ON DELETE RESTRICT ON UPDATE CASCADE;
        EXCEPTION WHEN OTHERS THEN NULL;
END $$;

GRANT SELECT, INSERT, DELETE, UPDATE ON TABLE sync.tbl_tii_submissions TO vilesci;
GRANT SELECT ON TABLE sync.tbl_tii_submissions TO web;

