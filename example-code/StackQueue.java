public class StackQueue {
   public static void main(String[] args) {
      Stack<String> stack = new Stack<>();
      Queue<String> queue = new Queue<>();
      stack.push("first");
      stack.push("second");
      queue.enqueue("first");
      queue.enqueue("second");
      for (String s : stack) 
         System.out.println("stack contains " + s);
      for (String s : queue)
         System.out.println("queue contains " + s);
      while (!stack.isEmpty())
         System.out.println(stack.pop());
      while (!queue.isEmpty())
         System.out.println(queue.dequeue());
   }
}