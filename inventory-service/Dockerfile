FROM golang:1.23-alpine AS builder

RUN apk add --no-cache protobuf protobuf-dev

RUN go install google.golang.org/protobuf/cmd/protoc-gen-go@v1.34.2
RUN go install google.golang.org/grpc/cmd/protoc-gen-go-grpc@v1.4.0

WORKDIR /app

COPY inventory-service/go.mod ./

RUN go get google.golang.org/grpc@v1.62.1 && \
    go get google.golang.org/protobuf@v1.34.1 && \
    go get gorm.io/gorm@v1.25.9 && \
    go get gorm.io/driver/mysql@v1.5.6

COPY protos/inventory.proto ./protos/inventory.proto

RUN protoc --go_out=. --go_opt=paths=source_relative \
    --go-grpc_out=. --go-grpc_opt=paths=source_relative \
    protos/inventory.proto

COPY inventory-service/main.go .
RUN go mod tidy
RUN go build -o inventory-service main.go

FROM alpine:latest
WORKDIR /app
COPY --from=builder /app/inventory-service .
EXPOSE 50051
CMD ["./inventory-service"]
