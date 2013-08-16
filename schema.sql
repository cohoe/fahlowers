DROP TABLE IF EXISTS "scans";

CREATE TABLE "scans" (
	"user_id" integer,
	"timestamp" integer,
	"follower_id" integer,
	"follower_name" text,
	"follower_handle" text,
	"last_access_timestamp" integer,
	PRIMARY KEY ("user_id","timestamp","follower_id")
);
