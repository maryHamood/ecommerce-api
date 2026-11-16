package main

import (
	"context"
	"fmt"
	"log"
	"net"
	"os"

	pb "inventory-service/protos"

	"google.golang.org/grpc"
	"gorm.io/driver/mysql"
	"gorm.io/gorm"
)


type Inventory struct {
	ProductID string `gorm:"primaryKey"`
	Amount    int
}

type Reservation struct {
	ID        uint `gorm:"primaryKey"`
	OrderID   string
	ProductID string
	Quantity  int
}


type server struct {
	pb.UnimplementedInventoryServiceServer
	db *gorm.DB
}




func (s *server) InitInventory(ctx context.Context, req *pb.InitRequest) (*pb.OperationResponse, error) {
	s.db.Create(&Inventory{ProductID: req.ProductId, Amount: int(req.InitialQuantity)})
	return &pb.OperationResponse{Success: true, Message: "Inventory initialized"}, nil
}


func (s *server) DeleteInventory(ctx context.Context, req *pb.DeleteRequest) (*pb.OperationResponse, error) {
	s.db.Where("product_id = ?", req.ProductId).Delete(&Inventory{})
	return &pb.OperationResponse{Success: true, Message: "Inventory deleted"}, nil
}


func (s *server) CheckStock(ctx context.Context, req *pb.StockRequest) (*pb.StockResponse, error) {
	var inv Inventory
	if err := s.db.First(&inv, "product_id = ?", req.ProductId).Error; err != nil {
		return &pb.StockResponse{Quantity: 0}, nil
	}
	return &pb.StockResponse{Quantity: int32(inv.Amount)}, nil
}


func (s *server) ReserveStock(ctx context.Context, req *pb.ReserveRequest) (*pb.OperationResponse, error) {

	tx := s.db.Begin()
	if tx.Error != nil {
		return &pb.OperationResponse{Success: false, Message: "DB Error"}, nil
	}

	for _, item := range req.Items {

		result := tx.Model(&Inventory{}).
			Where("product_id = ? AND amount >= ?", item.ProductId, item.Quantity).
			Update("amount", gorm.Expr("amount - ?", item.Quantity))

		if result.RowsAffected == 0 {
			tx.Rollback()
			return &pb.OperationResponse{Success: false, Message: "Insufficient stock for product " + item.ProductId}, nil
		}


		tx.Create(&Reservation{OrderID: req.OrderId, ProductID: item.ProductId, Quantity: int(item.Quantity)})
	}

	tx.Commit()
	return &pb.OperationResponse{Success: true, Message: "Stock reserved"}, nil
}


func (s *server) ReleaseStock(ctx context.Context, req *pb.ReleaseRequest) (*pb.OperationResponse, error) {
	var reservations []Reservation
	s.db.Where("order_id = ?", req.OrderId).Find(&reservations)

	for _, res := range reservations {
		s.db.Model(&Inventory{}).Where("product_id = ?", res.ProductID).
			Update("amount", gorm.Expr("amount + ?", res.Quantity))
	}

	s.db.Where("order_id = ?", req.OrderId).Delete(&Reservation{})
	return &pb.OperationResponse{Success: true, Message: "Stock released"}, nil
}


func main() {

	dbHost := os.Getenv("DB_HOST")
	dbUser := os.Getenv("DB_USER")
	dbPass := os.Getenv("DB_PASS")
	dbName := os.Getenv("DB_NAME")
	dsn := fmt.Sprintf("%s:%s@tcp(%s:3306)/%s?charset=utf8mb4&parseTime=True&loc=Local", dbUser, dbPass, dbHost, dbName)

	db, err := gorm.Open(mysql.Open(dsn), &gorm.Config{})
	if err != nil {
		log.Fatalf("Failed to connect to database: %v", err)
	}


	db.AutoMigrate(&Inventory{}, &Reservation{})


	lis, err := net.Listen("tcp", ":50051")
	if err != nil {
		log.Fatalf("Failed to listen: %v", err)
	}

	grpcServer := grpc.NewServer()
	pb.RegisterInventoryServiceServer(grpcServer, &server{db: db})

	log.Println("Inventory gRPC Server running on port 50051")
	grpcServer.Serve(lis)
}
