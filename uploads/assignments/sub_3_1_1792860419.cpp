#include <iostream>
using namespace std;

struct Node {
    int data;
    struct Node *next;
};
struct Node *head=NULL;
void insertion(){
    struct Node *newNode=(struct Node*)malloc(sizeof(struct Node));
    int val;
    cout<<"Enter The Element:";
    cin>>val;
    newNode->data=val;
    newNode->next=head;
    head=newNode;
    cout<<"Insert Element:"<<val;
}

void traversal(){
    if(head==NULL){
        cout<<"\n Empty List";
    }
    struct Node *ptr=head;

    while(ptr!=NULL){
        cout<<"\n "<<ptr->data;
        ptr=ptr->next;    
    }
   
}

int main(){
    int op;
    while(1){
        cout<<"\n 1.Insertion 2.Traversal";
        cout<<"[1-2]:";
        cin>>op;
        switch(op){
            case 1:
                insertion();
                break;
            case 2:
                traversal();
                break;
            default:
                cout<<"\n Invalid Choice";        
        }
    }
}