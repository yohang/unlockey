variable "IMAGE_PREFIX" {
  default = "ghcr.io/yohang/unlockey/"
}

variable "TAGS" {
  default = "latest"
}

group "default" {
  targets = ["backend-php", "agent"]
}

target "backend-php" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}backend-php:${t}"]
  cache-from = ["type=registry,ref=${IMAGE_PREFIX}backend-php:cache"]
  cache-to   = ["type=registry,ref=${IMAGE_PREFIX}backend-php:cache,mode=max"]
}

target "agent" {
  tags = [for t in split(",", TAGS) : "${IMAGE_PREFIX}agent:${t}"]
  cache-from = ["type=registry,ref=${IMAGE_PREFIX}agent:cache"]
  cache-to   = ["type=registry,ref=${IMAGE_PREFIX}agent:cache,mode=max"]
}
