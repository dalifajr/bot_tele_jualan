from __future__ import annotations

from functools import lru_cache
from pathlib import Path

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    bot_token: str = Field(default="", alias="BOT_TOKEN")
    database_url: str = Field(default="sqlite:///./data/bot_jualan.db", alias="DATABASE_URL")
    user_role_file: str = Field(default="./user_role.txt", alias="USER_ROLE_FILE")
    listener_shared_secret: str = Field(default="change-me", alias="LISTENER_SHARED_SECRET")
    listener_signature_ttl_seconds: int = Field(default=300, alias="LISTENER_SIGNATURE_TTL_SECONDS")
    listener_payment_match_window_minutes: int = Field(default=60, alias="LISTENER_PAYMENT_MATCH_WINDOW_MINUTES")
    listener_require_reference: bool = Field(default=False, alias="LISTENER_REQUIRE_REFERENCE")
    listener_allow_legacy_secret: bool = Field(default=False, alias="LISTENER_ALLOW_LEGACY_SECRET")
    qris_image_path: str = Field(default="./data/qris.png", alias="QRIS_IMAGE_PATH")
    github_repo_url: str = Field(default="https://github.com/dalifajr/bot-jualan.git", alias="GITHUB_REPO_URL")
    update_branch: str = Field(default="", alias="UPDATE_BRANCH")

    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    @property
    def project_root(self) -> Path:
        return Path(__file__).resolve().parents[3]

    @property
    def role_file_path(self) -> Path:
        return (self.project_root / self.user_role_file).resolve()

    @property
    def qris_file_path(self) -> Path:
        return (self.project_root / self.qris_image_path).resolve()


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    return Settings()
