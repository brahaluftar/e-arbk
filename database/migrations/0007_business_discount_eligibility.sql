SET XACT_ABORT ON;
GO

ALTER TABLE dbo.ARBK_LIST ADD
    pronare_grua bit NULL,
    pronesia_grua decimal(5,2) NULL,
    pronar_veteran bit NULL,
    perqindja_veteran decimal(5,2) NULL,
    tarifa_me_lirim decimal(18,2) NULL;
GO

ALTER TABLE dbo.ARBK_LIST ADD
    CONSTRAINT CK_ARBK_LIST_pronesia_grua
        CHECK (pronesia_grua IS NULL OR pronesia_grua BETWEEN 0 AND 100),
    CONSTRAINT CK_ARBK_LIST_perqindja_veteran
        CHECK (perqindja_veteran IS NULL OR perqindja_veteran BETWEEN 0 AND 100),
    CONSTRAINT CK_ARBK_LIST_tarifa_me_lirim
        CHECK (tarifa_me_lirim IS NULL OR tarifa_me_lirim >= 0),
    CONSTRAINT CK_ARBK_LIST_pronare_grua_consistency
        CHECK (pronare_grua IS NULL OR pronare_grua = 1 OR pronesia_grua IS NULL OR pronesia_grua = 0),
    CONSTRAINT CK_ARBK_LIST_pronar_veteran_consistency
        CHECK (pronar_veteran IS NULL OR pronar_veteran = 1 OR perqindja_veteran IS NULL OR perqindja_veteran = 0);
GO
